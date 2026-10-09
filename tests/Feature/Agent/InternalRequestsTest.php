<?php

use App\Domain\Accounts\Enums\Permission;
use App\Domain\Accounts\Enums\TicketAccess;
use App\Domain\Accounts\Models\Group;
use App\Domain\Mail\Enums\EmailTemplateEvent;
use App\Domain\Mail\Models\EmailTemplate;
use App\Domain\Tickets\Enums\TicketChannel;
use App\Domain\Tickets\Enums\TicketStatus;
use App\Domain\Tickets\Models\Ticket;
use App\Domain\Tickets\Models\TicketMessage;
use App\Domain\Tickets\Notifications\TicketReplied;
use App\Mail\TicketEmail;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->sales = Group::factory()->create(['name' => 'Sales']);
    $this->support = Group::factory()->create(['name' => 'Support']);
    $this->seller = User::factory()->agent()->create(['name' => 'Sam Seller']);
    $this->seller->groups()->attach($this->sales);
    $this->supporter = User::factory()->agent()->create(['name' => 'Sue Support']);
    $this->supporter->groups()->attach($this->support);
});

/**
 * An internal request from the seller to Support, assigned to the supporter.
 */
function internalRequest(object $test, TicketStatus $status = TicketStatus::Open): Ticket
{
    $ticket = Ticket::factory()->assignedTo($test->supporter)->status($status)->create([
        'channel' => TicketChannel::Internal,
        'requester_id' => $test->seller->id,
        'group_id' => $test->support->id,
    ]);
    TicketMessage::factory()->for($ticket)->create(['author_id' => $test->seller->id, 'body' => '<p>Can you check the invoice?</p>']);
    TicketMessage::factory()->for($ticket)->internal()->create(['author_id' => $test->supporter->id, 'body' => '<p>Support only: billing bug again</p>']);

    return $ticket;
}

test('an agent sends a request to another department, which hears about it', function () {
    Mail::fake();
    EmailTemplate::for(EmailTemplateEvent::AgentNewTicketAlert)->forceFill(['is_active' => true])->save();
    $customerTicket = Ticket::factory()->create(['group_id' => $this->sales->id]);

    $this->actingAs($this->seller)
        ->post(route('agent.requests.store'), [
            'group_id' => $this->support->id,
            'subject' => 'Refund for Acme',
            'body' => '<p>Please refund the last invoice.</p>',
            'priority' => 'high',
            'related_ticket_id' => $customerTicket->id,
        ])
        ->assertRedirect();

    $ticket = Ticket::query()->where('subject', 'Refund for Acme')->sole();
    expect($ticket->channel)->toBe(TicketChannel::Internal)
        ->and($ticket->requester_id)->toBe($this->seller->id)
        ->and($ticket->group_id)->toBe($this->support->id)
        ->and($ticket->last_customer_reply_at)->not->toBeNull()
        ->and($ticket->linkedTickets()->pluck('tickets.id')->all())->toBe([$customerTicket->id]);

    Mail::assertQueued(TicketEmail::class, fn (TicketEmail $mail): bool => $mail->event === EmailTemplateEvent::AgentNewTicketAlert && $mail->hasTo($this->supporter->email));
    Mail::assertNotQueued(TicketEmail::class, fn (TicketEmail $mail): bool => $mail->hasTo($this->seller->email));
});

test('sending a request takes the permission to create tickets', function () {
    $viewer = User::factory()->withPermissions([Permission::ReplyToTickets])->create();

    $this->actingAs($viewer)->get(route('agent.requests.create'))->assertForbidden();
    $this->actingAs($viewer)
        ->post(route('agent.requests.store'), ['group_id' => $this->support->id, 'subject' => 'Hi', 'body' => '<p>Hi</p>'])
        ->assertForbidden();
});

test('a related ticket must be one the agent can see', function () {
    $requester = User::factory()->withPermissions([Permission::CreateTickets], TicketAccess::Assigned)->create();
    $hidden = Ticket::factory()->create();

    $this->actingAs($requester)
        ->post(route('agent.requests.store'), ['group_id' => $this->support->id, 'subject' => 'Hi', 'body' => '<p>Hi</p>', 'related_ticket_id' => $hidden->id])
        ->assertSessionHasErrors('related_ticket_id');
});

test('the requesting agent follows the request without the team\'s internal notes', function () {
    $ticket = internalRequest($this);

    $this->actingAs($this->seller)->get(route('agent.tickets.show', $ticket))->assertRedirect(route('agent.requests.show', $ticket));
    $this->actingAs($this->seller)
        ->get(route('agent.requests.show', $ticket))
        ->assertInertia(fn (Assert $page) => $page
            ->component('agent/requests/show')
            ->has('messages', 1)
            ->where('messages.0.body', '<p>Can you check the invoice?</p>')
            ->where('canReply', true));
    $this->actingAs($this->seller)
        ->get(route('agent.requests.index'))
        ->assertInertia(fn (Assert $page) => $page->has('tickets.data', 1));

    // The team working it stays in the agent workspace.
    $this->actingAs($this->supporter)->get(route('agent.requests.show', $ticket))->assertRedirect(route('agent.tickets.show', $ticket));
    $this->actingAs($this->supporter)->get(route('agent.tickets.show', $ticket))->assertInertia(fn (Assert $page) => $page
        ->has('messages', 2)
        ->where('requester.departments', ['Sales']));

    Sanctum::actingAs($this->seller, ['tickets:read']);
    $this->getJson(route('api.v1.tickets.messages.index', $ticket))->assertOk()->assertJsonCount(1, 'data');
});

test('the requesting agent replies like a requester, without touching the ticket', function () {
    $requester = User::factory()->withPermissions([Permission::CreateTickets, Permission::UpdateTickets])->create();
    $ticket = internalRequest($this);
    $ticket->update(['requester_id' => $requester->id]);

    $this->actingAs($requester)
        ->post(route('agent.requests.replies.store', $ticket), ['body' => '<p>Any news?</p>'])
        ->assertSessionHasNoErrors();
    $this->actingAs($requester)
        ->post(route('agent.tickets.messages.store', $ticket), ['body' => '<p>Note</p>', 'is_internal' => true])
        ->assertForbidden();
    $this->actingAs($requester)
        ->patch(route('agent.tickets.update', $ticket), ['priority' => 'urgent'])
        ->assertForbidden();

    expect($ticket->messages()->latest('id')->first()->body)->toBe('<p>Any news?</p>');
});

test('a reply from the requesting agent reopens the request and alerts the assignee', function () {
    Notification::fake();
    $ticket = internalRequest($this, TicketStatus::Pending);

    $this->actingAs($this->seller)
        ->post(route('agent.requests.replies.store', $ticket), ['body' => '<p>Here is the invoice number.</p>'])
        ->assertSessionHasNoErrors();

    $ticket->refresh();
    expect($ticket->status)->toBe(TicketStatus::Open)
        ->and($ticket->last_customer_reply_at)->not->toBeNull()
        ->and($ticket->first_responded_at)->toBeNull();
    Notification::assertSentTo($this->supporter, TicketReplied::class);
});

test('a reply from the team is the first response and reaches the requesting agent', function () {
    Notification::fake();
    $ticket = internalRequest($this);

    $this->actingAs($this->supporter)
        ->post(route('agent.tickets.messages.store', $ticket), ['body' => '<p>Refunded.</p>', 'is_internal' => false])
        ->assertSessionHasNoErrors();

    expect($ticket->fresh()->first_responded_at)->not->toBeNull();
    Notification::assertSentTo($this->seller, TicketReplied::class);
});
