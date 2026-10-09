<?php

use App\Domain\Accounts\Enums\TicketAccess;
use App\Domain\Tickets\Actions\AddMessage;
use App\Domain\Tickets\Actions\RateTicket;
use App\Domain\Tickets\Actions\UpdateTicket;
use App\Domain\Tickets\Enums\TicketStatus;
use App\Domain\Tickets\Events\MessageCreated;
use App\Domain\Tickets\Events\TicketRefreshed;
use App\Domain\Tickets\Events\TicketUpdated;
use App\Domain\Tickets\Models\Ticket;
use App\Models\User;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    $this->agent = User::factory()->agent()->create();
    $this->cc = User::factory()->create();
    $this->ticket = Ticket::factory()->status(TicketStatus::Open)->create();
    $this->ticket->collaborators()->attach($this->cc);
});

/**
 * @return list<string>
 */
function channelNames(object $event): array
{
    return array_map(fn (PrivateChannel $channel): string => $channel->name, $event->broadcastOn());
}

/**
 * Switch to a real (signing) broadcaster so /broadcasting/auth runs the channel callbacks.
 */
function useSigningBroadcaster(): void
{
    config([
        'broadcasting.default' => 'reverb',
        'broadcasting.connections.reverb.key' => 'key',
        'broadcasting.connections.reverb.secret' => 'secret',
        'broadcasting.connections.reverb.app_id' => 'app',
    ]);
    Broadcast::forgetDrivers();
    require base_path('routes/channels.php');
}

test('only people on a ticket can join its channels', function () {
    useSigningBroadcaster();
    $auth = fn (User $user, string $channel) => $this->actingAs($user)
        ->post('/broadcasting/auth', ['socket_id' => '1234.5678', 'channel_name' => $channel]);
    $ticketChannel = 'private-kitedesk.tickets.'.$this->ticket->id;
    $presence = 'presence-kitedesk.staff.tickets.'.$this->ticket->id;

    $auth($this->ticket->requester, $ticketChannel)->assertOk();
    $auth($this->cc, $ticketChannel)->assertOk();
    $auth(User::factory()->create(), $ticketChannel)->assertForbidden();
    $auth($this->ticket->requester, 'private-kitedesk.staff.tickets')->assertForbidden();
    $auth($this->ticket->requester, $presence)->assertForbidden();
    $auth($this->agent, $presence)->assertOk();
    $auth(User::factory()->withPermissions([], TicketAccess::Assigned)->create(), $presence)->assertForbidden();
});

test('channels carry the installation scope, and other scopes are refused', function () {
    useSigningBroadcaster();
    config(['broadcasting.channel_scope' => 'acme']);
    $auth = fn (string $channel) => $this->actingAs($this->agent)
        ->post('/broadcasting/auth', ['socket_id' => '1234.5678', 'channel_name' => $channel]);

    $auth('private-acme.staff.tickets')->assertOk();
    $auth('private-kitedesk.staff.tickets')->assertForbidden();
    expect(channelNames(new TicketRefreshed($this->ticket, customerVisible: false)))->toBe(['private-acme.staff.tickets', 'private-acme.staff.tickets.'.$this->ticket->id]);
});

test('customers only hear about ticket changes they can see', function () {
    Event::fake([TicketUpdated::class]);
    $customerChannels = [
        'private-kitedesk.tickets.'.$this->ticket->id,
        'private-kitedesk.App.Models.User.'.$this->ticket->requester_id,
        'private-kitedesk.App.Models.User.'.$this->cc->id,
    ];

    app(UpdateTicket::class)->handle($this->ticket, ['priority' => 'urgent', 'tags' => ['vip']]);
    app(UpdateTicket::class)->handle($this->ticket, ['status' => TicketStatus::Pending]);

    $dispatched = Event::dispatched(TicketUpdated::class)->map(fn (array $args) => channelNames($args[0]));

    expect($dispatched[0])->not->toContain(...$customerChannels)
        ->and($dispatched[1])->toContain(...$customerChannels);
});

test('a person removed from the CC list hears about it, so the ticket leaves their list', function () {
    Event::fake([TicketUpdated::class]);

    app(UpdateTicket::class)->handle($this->ticket, ['collaborator_ids' => []]);

    Event::assertDispatched(TicketUpdated::class, fn (TicketUpdated $event) => in_array('private-kitedesk.App.Models.User.'.$this->cc->id, channelNames($event), true));
});

test('internal notes stay on the staff channels', function () {
    Event::fake([MessageCreated::class]);

    app(AddMessage::class)->handle($this->ticket, $this->agent, '<p>Note</p>', isInternal: true);
    app(AddMessage::class)->handle($this->ticket, $this->agent, '<p>Reply</p>');

    $dispatched = Event::dispatched(MessageCreated::class)->map(fn (array $args) => channelNames($args[0]));

    expect($dispatched[0])->toBe(['private-kitedesk.staff.tickets', 'private-kitedesk.staff.tickets.'.$this->ticket->id])
        ->and($dispatched[1])->toContain('private-kitedesk.tickets.'.$this->ticket->id, 'private-kitedesk.App.Models.User.'.$this->ticket->requester_id);
});

test('ratings, SLA breaches and merges refresh open pages', function () {
    Event::fake([TicketRefreshed::class]);
    $solved = Ticket::factory()->status(TicketStatus::Solved)->create();
    $overdue = Ticket::factory()->assignedTo($this->agent)->create(['first_response_due_at' => now()->subHour()]);

    app(RateTicket::class)->handle($solved, $solved->requester, 5);
    $this->artisan('sla:check-breaches')->assertSuccessful();
    $this->actingAs($this->agent)->post(route('agent.tickets.merge', $this->ticket), ['target_id' => $solved->id]);

    Event::assertDispatched(TicketRefreshed::class, fn (TicketRefreshed $event) => $event->ticket->is($solved) && $event->customerVisible);
    Event::assertDispatched(TicketRefreshed::class, fn (TicketRefreshed $event) => $event->ticket->is($overdue)
        && ! $event->customerVisible
        && ! in_array('private-kitedesk.tickets.'.$overdue->id, channelNames($event), true));
    Event::assertDispatchedTimes(TicketRefreshed::class, 3);
});

test('pages tell the browser how to reach Reverb, or that live updates are off', function () {
    $this->actingAs($this->agent)->get(route('agent.tickets.index'))->assertSee('<script id="realtime-options" type="application/json">null</script>', false);

    config([
        'broadcasting.default' => 'reverb',
        'broadcasting.connections.reverb.key' => 'public-key',
        'broadcasting.connections.reverb.options' => ['host' => 'ws.example.com', 'port' => 443, 'scheme' => 'https'],
    ]);

    $this->actingAs($this->agent)->get(route('agent.tickets.index'))
        ->assertSee('{"key":"public-key","wsHost":"ws.example.com","wsPort":443,"wssPort":443,"forceTLS":true}', false);
});
