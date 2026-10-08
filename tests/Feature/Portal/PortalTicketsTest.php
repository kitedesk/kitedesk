<?php

use App\Domain\Tickets\Enums\TicketStatus;
use App\Domain\Tickets\Models\Ticket;
use App\Domain\Tickets\Models\TicketField;
use App\Domain\Tickets\Models\TicketMessage;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->customer = User::factory()->create();
});

test('customers can submit a request', function () {
    $response = $this->actingAs($this->customer)->post(route('portal.tickets.store'), [
        'subject' => 'Cannot export my data',
        'body' => '<p>The export button does nothing.</p>',
    ]);

    $ticket = Ticket::query()->sole();
    $response->assertRedirect(route('portal.tickets.show', $ticket));
    expect($ticket->requester_id)->toBe($this->customer->id)
        ->and($ticket->channel->value)->toBe('portal');
});

test('customers only see their own requests', function () {
    $own = Ticket::factory()->for($this->customer, 'requester')->create();
    $other = Ticket::factory()->create();

    $this->actingAs($this->customer)
        ->get(route('portal.tickets.index'))
        ->assertInertia(fn (Assert $page) => $page->component('portal/tickets/index')->has('tickets.data', 1)->where('tickets.data.0.id', $own->id));

    $this->actingAs($this->customer)->get(route('portal.tickets.show', $other))->assertForbidden();
});

test('internal notes, SLA data and internal fields are hidden from customers', function () {
    TicketField::factory()->create(['key' => 'order_number', 'is_visible_to_customers' => true]);
    TicketField::factory()->create(['key' => 'fraud_score', 'is_visible_to_customers' => false]);
    $ticket = Ticket::factory()->for($this->customer, 'requester')->create(['custom_fields' => ['order_number' => 'A-1', 'fraud_score' => '97']]);
    TicketMessage::factory()->for($ticket)->create();
    TicketMessage::factory()->for($ticket)->internal()->create(['body' => '<p>Secret</p>']);

    $this->actingAs($this->customer)
        ->get(route('portal.tickets.show', $ticket))
        ->assertInertia(fn (Assert $page) => $page
            ->has('messages', 1)
            ->missing('ticket.sla')
            ->missing('ticket.tags')
            ->where('ticket.custom_fields', ['order_number' => 'A-1']));
});

test('a customer reply reopens a solved request and can mark it solved again', function () {
    $ticket = Ticket::factory()->for($this->customer, 'requester')->status(TicketStatus::Solved)->create();

    $this->actingAs($this->customer)
        ->post(route('portal.tickets.replies.store', $ticket), ['body' => '<p>Still broken</p>']);
    expect($ticket->refresh()->status)->toBe(TicketStatus::Open);

    $this->actingAs($this->customer)
        ->post(route('portal.tickets.replies.store', $ticket), ['body' => '<p>Fixed now, thanks</p>', 'mark_solved' => true]);
    expect($ticket->refresh()->status)->toBe(TicketStatus::Solved);
});

test('customers cannot reply to closed requests or to other customers requests', function () {
    $closed = Ticket::factory()->for($this->customer, 'requester')->status(TicketStatus::Closed)->create();
    $other = Ticket::factory()->create();

    $this->actingAs($this->customer)
        ->post(route('portal.tickets.replies.store', $closed), ['body' => '<p>Hi</p>'])
        ->assertForbidden();

    $this->actingAs($this->customer)
        ->post(route('portal.tickets.replies.store', $other), ['body' => '<p>Hi</p>'])
        ->assertForbidden();
});

test('attachments on internal notes cannot be downloaded by customers', function () {
    Storage::fake('local');
    $ticket = Ticket::factory()->for($this->customer, 'requester')->create();
    $note = TicketMessage::factory()->for($ticket)->internal()->create();
    $media = $note->addMedia(UploadedFile::fake()->create('logs.txt', 2))->toMediaCollection('attachments');

    $this->actingAs($this->customer)->get(route('attachments.show', $media))->assertNotFound();
    $this->actingAs(User::factory()->agent()->create())->get(route('attachments.show', $media))->assertOk();
});

test('customers see who replied to their request', function () {
    $agent = User::factory()->agent()->create(['name' => 'Ana']);
    $ticket = Ticket::factory()->for($this->customer, 'requester')->create();
    TicketMessage::factory()->for($ticket)->create(['author_id' => $agent->id, 'is_internal' => false]);

    $this->actingAs($this->customer)
        ->get(route('portal.tickets.show', $ticket))
        ->assertInertia(fn (Assert $page) => $page->where('messages.0.author.name', 'Ana'));
});
