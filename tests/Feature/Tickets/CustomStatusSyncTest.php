<?php

use App\Domain\Sla\Models\SlaPolicy;
use App\Domain\Tickets\Actions\AddMessage;
use App\Domain\Tickets\Actions\UpdateTicket;
use App\Domain\Tickets\Enums\TicketStatus;
use App\Domain\Tickets\Models\CustomStatus;
use App\Domain\Tickets\Models\Ticket;
use App\Domain\Tickets\Support\CustomStatuses;
use App\Domain\Workflows\Enums\WorkflowTrigger;
use App\Domain\Workflows\Models\Workflow;
use App\Models\User;

require_once __DIR__.'/../Workflows/helpers.php';

beforeEach(function () {
    $this->agent = User::factory()->agent()->create();
    $this->vendor = CustomStatus::factory()->category(TicketStatus::Pending)->create(['name' => 'Waiting on vendor']);
});

test('new tickets get the default status of their category', function () {
    $ticket = Ticket::factory()->status(TicketStatus::Open)->create();

    expect($ticket->ticket_status_id)->toBe(CustomStatuses::defaultFor(TicketStatus::Open)->id);
});

test('choosing a status moves the ticket into its category and pauses the SLA like any pending status', function () {
    SlaPolicy::factory()->create();
    $ticket = workflowTicket();
    app(UpdateTicket::class)->handle($ticket, ['assignee_id' => $this->agent->id]);

    $this->actingAs($this->agent)
        ->patch(route('agent.tickets.update', $ticket), ['ticket_status_id' => $this->vendor->id])
        ->assertSessionHasNoErrors();

    $ticket->refresh();
    expect($ticket->status)->toBe(TicketStatus::Pending)
        ->and($ticket->ticket_status_id)->toBe($this->vendor->id)
        ->and($ticket->resolution_remaining_minutes)->not->toBeNull();
});

test('changing only the category resets the status to the category default', function () {
    $ticket = Ticket::factory()->status(TicketStatus::Open)->create();
    app(UpdateTicket::class)->handle($ticket, ['ticket_status_id' => $this->vendor->id]);

    // A customer reply reopens pending tickets.
    app(AddMessage::class)->handle($ticket, $ticket->requester, '<p>Any news?</p>');

    expect($ticket->refresh()->status)->toBe(TicketStatus::Open)
        ->and($ticket->ticket_status_id)->toBe(CustomStatuses::defaultFor(TicketStatus::Open)->id);
});

test('agents reply and set a custom status in one go', function () {
    $ticket = Ticket::factory()->status(TicketStatus::Open)->create();

    $this->actingAs($this->agent)->post(route('agent.tickets.messages.store', $ticket), [
        'body' => '<p>Asked the vendor.</p>',
        'ticket_status_id' => $this->vendor->id,
    ])->assertSessionHasNoErrors();

    expect($ticket->refresh()->ticket_status_id)->toBe($this->vendor->id)
        ->and($ticket->status)->toBe(TicketStatus::Pending);
});

test('a status picked from the submit menu becomes the agent\'s default for replies', function () {
    $ticket = Ticket::factory()->status(TicketStatus::Open)->create();
    $open = CustomStatuses::defaultFor(TicketStatus::Open);

    $this->actingAs($this->agent)->post(route('agent.tickets.messages.store', $ticket), [
        'body' => '<p>Using the default button.</p>',
        'ticket_status_id' => $open->id,
    ]);
    expect($this->agent->refresh()->preferences['reply_status_id'] ?? null)->toBeNull();

    $this->actingAs($this->agent)->post(route('agent.tickets.messages.store', $ticket), [
        'body' => '<p>Asked the vendor.</p>',
        'ticket_status_id' => $this->vendor->id,
        'remember_status' => true,
    ])->assertSessionHasNoErrors();

    $this->actingAs($this->agent)
        ->get(route('agent.tickets.show', Ticket::factory()->create()))
        ->assertInertia(fn ($page) => $page->where('replyStatusId', $this->vendor->id));
});

test('inactive statuses and closed ones cannot be chosen', function () {
    $ticket = Ticket::factory()->status(TicketStatus::Open)->create();
    $inactive = CustomStatus::factory()->inactive()->create();

    $this->actingAs($this->agent)
        ->patch(route('agent.tickets.update', $ticket), ['ticket_status_id' => $inactive->id])
        ->assertSessionHasErrors('ticket_status_id');

    $this->actingAs($this->agent)->post(route('agent.tickets.messages.store', $ticket), [
        'body' => '<p>Done</p>',
        'ticket_status_id' => CustomStatuses::defaultFor(TicketStatus::Closed)->id,
    ])->assertSessionHasErrors('ticket_status_id');
});

test('workflows can test and set the status', function () {
    Workflow::factory()->flow(WorkflowTrigger::TicketCreated, [
        'check' => ['if', ['conditions' => workflowCondition('custom_status', 'is', (string) CustomStatuses::defaultFor(TicketStatus::New)->id)]],
        'set' => ['update_ticket', ['status' => 'open', 'ticket_status_id' => (string) $this->vendor->id]],
    ], [['trigger', 'out', 'check'], ['check', 'true', 'set']])->create();

    $ticket = workflowTicket()->refresh();

    expect($ticket->ticket_status_id)->toBe($this->vendor->id)
        ->and($ticket->status)->toBe(TicketStatus::Pending);
});
