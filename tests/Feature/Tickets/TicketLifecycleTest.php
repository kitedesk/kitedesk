<?php

use App\Domain\Accounts\Models\Organization;
use App\Domain\Sla\Models\SlaPolicy;
use App\Domain\Tickets\Actions\AddMessage;
use App\Domain\Tickets\Actions\CreateTicket;
use App\Domain\Tickets\Actions\UpdateTicket;
use App\Domain\Tickets\Enums\TicketChannel;
use App\Domain\Tickets\Enums\TicketPriority;
use App\Domain\Tickets\Enums\TicketStatus;
use App\Domain\Tickets\Events\MessageCreated;
use App\Domain\Tickets\Events\TicketCreated;
use App\Domain\Tickets\Events\TicketUpdated;
use App\Domain\Tickets\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    $this->travelTo('2026-10-12 10:00:00');
    // 24/7 policy: normal priority = 8h first response, 16h next reply, 48h resolution.
    SlaPolicy::factory()->create();
});

function openTicket(?User $requester = null, array $attributes = []): Ticket
{
    return app(CreateTicket::class)->handle(
        $requester ?? User::factory()->create(),
        ['subject' => 'Printer on fire', 'body' => '<p>Help!</p>', ...$attributes],
        TicketChannel::Portal,
    );
}

test('creating a ticket stores the first message and starts the SLA clocks', function () {
    Event::fake([TicketCreated::class]);

    $ticket = openTicket(attributes: ['body' => '<p>Help!</p><script>alert(1)</script>', 'tags' => ['Hardware Issue']]);

    expect($ticket->status)->toBe(TicketStatus::New)
        ->and($ticket->messages()->sole()->body)->toBe('<p>Help!</p>')
        ->and($ticket->tags->pluck('name')->all())->toBe(['hardware_issue'])
        ->and($ticket->first_response_due_at->toDateTimeString())->toBe('2026-10-12 18:00:00')
        ->and($ticket->resolution_due_at->toDateTimeString())->toBe('2026-10-14 10:00:00');

    Event::assertDispatched(TicketCreated::class);
});

test('tickets are linked to the organization that owns the requester email domain', function () {
    $organization = Organization::factory()->create(['domains' => ['acme.test']]);

    $ticket = openTicket(User::factory()->create(['email' => 'jane@ACME.test']));

    expect($ticket->organization_id)->toBe($organization->id);
});

test('a public agent reply records the first response and opens the ticket', function () {
    Event::fake([MessageCreated::class, TicketUpdated::class]);
    $ticket = openTicket();

    $this->travel(30)->minutes();
    app(AddMessage::class)->handle($ticket, User::factory()->agent()->create(), '<p>On it</p>');

    $ticket->refresh();
    expect($ticket->status)->toBe(TicketStatus::Open)
        ->and($ticket->first_responded_at->toDateTimeString())->toBe('2026-10-12 10:30:00')
        ->and($ticket->first_response_due_at)->toBeNull();

    Event::assertDispatched(MessageCreated::class);
    Event::assertDispatched(TicketUpdated::class, fn (TicketUpdated $event) => $event->changes['status'] === ['from' => 'new', 'to' => 'open']);
});

test('an internal note changes neither the status nor the SLA', function () {
    $ticket = openTicket();

    app(AddMessage::class)->handle($ticket, User::factory()->agent()->create(), '<p>Checking logs</p>', isInternal: true);

    $ticket->refresh();
    expect($ticket->status)->toBe(TicketStatus::New)
        ->and($ticket->first_responded_at)->toBeNull()
        ->and($ticket->first_response_due_at)->not->toBeNull();
});

test('a customer reply reopens a pending ticket and starts the next reply clock', function () {
    $requester = User::factory()->create();
    $ticket = openTicket($requester);
    app(AddMessage::class)->handle($ticket, User::factory()->agent()->create(), '<p>Can you send logs?</p>', statusAfter: TicketStatus::Pending);

    $this->travel(2)->hours();
    app(AddMessage::class)->handle($ticket->refresh(), $requester, '<p>Here they are</p>', channel: TicketChannel::Portal);

    $ticket->refresh();
    expect($ticket->status)->toBe(TicketStatus::Open)
        ->and($ticket->next_reply_due_at->toDateTimeString())->toBe('2026-10-13 04:00:00');
});

test('pending pauses the resolution clock and reopening resumes the remaining time', function () {
    $ticket = openTicket();
    $updateTicket = app(UpdateTicket::class);

    $this->travel(8)->hours();
    $updateTicket->handle($ticket, ['status' => TicketStatus::Pending]);

    expect($ticket->resolution_due_at)->toBeNull()
        ->and($ticket->resolution_remaining_minutes)->toBe(40 * 60);

    $this->travel(24)->hours();
    $updateTicket->handle($ticket, ['status' => TicketStatus::Open]);

    expect($ticket->resolution_due_at->toDateTimeString())->toBe('2026-10-15 10:00:00')
        ->and($ticket->resolution_remaining_minutes)->toBeNull();
});

test('solving a ticket stops every SLA clock and records when it was solved', function () {
    $ticket = openTicket();

    app(UpdateTicket::class)->handle($ticket, ['status' => TicketStatus::Solved]);

    expect($ticket->solved_at->toDateTimeString())->toBe('2026-10-12 10:00:00')
        ->and($ticket->first_response_due_at)->toBeNull()
        ->and($ticket->resolution_due_at)->toBeNull();
});

test('changing the priority recalculates SLA targets', function () {
    $ticket = openTicket();

    app(UpdateTicket::class)->handle($ticket, ['priority' => TicketPriority::Urgent]);

    expect($ticket->first_response_due_at->toDateTimeString())->toBe('2026-10-12 11:00:00');
});

test('assigning a new ticket opens it', function () {
    $ticket = openTicket();
    $agent = User::factory()->agent()->create();

    app(UpdateTicket::class)->handle($ticket, ['assignee_id' => $agent->id]);

    expect($ticket->status)->toBe(TicketStatus::Open);
});

test('tag changes are reported with the update', function () {
    Event::fake([TicketUpdated::class]);
    $ticket = openTicket(attributes: ['tags' => ['vip']]);

    app(UpdateTicket::class)->handle($ticket, ['tags' => ['vip', 'refund']]);

    Event::assertDispatched(TicketUpdated::class, fn (TicketUpdated $event) => $event->changes === ['tags' => ['from' => ['vip'], 'to' => ['refund', 'vip']]]);
});
