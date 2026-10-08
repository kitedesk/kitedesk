<?php

use App\Domain\Tickets\Enums\TicketStatus;
use App\Domain\Tickets\Events\MessageCreated;
use App\Domain\Tickets\Events\TicketCreated;
use App\Domain\Tickets\Models\Ticket;
use App\Domain\Tickets\Models\TicketMessage;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->agent = User::factory()->agent()->create();
    Sanctum::actingAs($this->agent, ['tickets:read', 'tickets:write']);
});

test('tickets can be listed with filters and pagination', function () {
    Ticket::factory()->count(3)->create();
    $urgent = Ticket::factory()->create(['priority' => 'urgent']);

    $this->getJson(route('api.v1.tickets.index', ['per_page' => 2]))
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('meta.total', 4)
        ->assertJsonPath('meta.per_page', 2);

    $this->getJson(route('api.v1.tickets.index', ['filter' => ['priority' => 'urgent']]))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $urgent->id)
        ->assertJsonStructure(['data' => [['id', 'subject', 'status', 'priority', 'requester' => ['id', 'email'], 'sla']]]);
});

test('tickets can be filtered by update time', function () {
    $this->travelTo('2026-10-01 10:00');
    Ticket::factory()->create();
    $this->travelTo('2026-10-05 10:00');
    $recent = Ticket::factory()->create();

    $this->getJson(route('api.v1.tickets.index', ['filter' => ['updated_since' => '2026-10-03T00:00:00Z']]))
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $recent->id);
});

test('per page is capped at 100', function () {
    $this->getJson(route('api.v1.tickets.index', ['per_page' => 500]))
        ->assertOk()
        ->assertJsonPath('meta.per_page', 100);
});

test('creating a ticket for a new email creates a customer and runs the domain action', function () {
    Event::fake([TicketCreated::class]);

    $response = $this->postJson(route('api.v1.tickets.store'), [
        'requester' => ['email' => 'New.Person@example.com', 'name' => 'New Person'],
        'subject' => 'Imported from CRM',
        'body' => '<p>Hello</p><script>alert(1)</script>',
        'priority' => 'high',
        'tags' => ['crm'],
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.subject', 'Imported from CRM')
        ->assertJsonPath('data.channel', 'api')
        ->assertJsonPath('data.priority', 'high')
        ->assertJsonPath('data.requester.email', 'new.person@example.com');

    $ticket = Ticket::query()->sole();
    expect($ticket->requester->isCustomer())->toBeTrue()
        ->and($ticket->messages()->sole()->body)->toBe('<p>Hello</p>');

    Event::assertDispatched(TicketCreated::class);
});

test('creating a ticket validates input', function () {
    $this->postJson(route('api.v1.tickets.store'), ['subject' => ''])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['subject', 'body', 'requester_id']);
});

test('tickets can be shown and updated', function () {
    $ticket = Ticket::factory()->create();

    $this->getJson(route('api.v1.tickets.show', $ticket))->assertOk()->assertJsonPath('data.id', $ticket->id);

    $this->patchJson(route('api.v1.tickets.update', $ticket), ['status' => 'pending', 'assignee_id' => $this->agent->id])
        ->assertOk()
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.assignee.id', $this->agent->id);
});

test('replies and internal notes can be added and listed', function () {
    Event::fake([MessageCreated::class]);
    $ticket = Ticket::factory()->create();

    $this->postJson(route('api.v1.tickets.messages.store', $ticket), ['body' => '<p>Working on it</p>', 'status' => 'pending'])
        ->assertCreated()
        ->assertJsonPath('data.is_internal', false)
        ->assertJsonPath('data.author.id', $this->agent->id);

    $this->postJson(route('api.v1.tickets.messages.store', $ticket), ['body' => '<p>Escalate</p>', 'is_internal' => true])
        ->assertCreated()
        ->assertJsonPath('data.is_internal', true);

    expect($ticket->refresh()->status)->toBe(TicketStatus::Pending)
        ->and($ticket->first_responded_at)->not->toBeNull();

    $this->getJson(route('api.v1.tickets.messages.index', $ticket))
        ->assertOk()
        ->assertJsonCount(2, 'data');

    Event::assertDispatchedTimes(MessageCreated::class, 2);
});

test('light agents cannot reply publicly through the API', function () {
    Sanctum::actingAs(User::factory()->lightAgent()->create(), ['tickets:write']);
    $ticket = Ticket::factory()->create();

    $this->postJson(route('api.v1.tickets.messages.store', $ticket), ['body' => '<p>Hi</p>'])->assertForbidden();
    $this->postJson(route('api.v1.tickets.messages.store', $ticket), ['body' => '<p>Note</p>', 'is_internal' => true])->assertCreated();
    $this->postJson(route('api.v1.tickets.messages.store', $ticket), ['body' => '<p>Note</p>', 'is_internal' => true, 'status' => 'solved'])->assertJsonValidationErrors('status');
    $this->postJson(route('api.v1.tickets.store'), ['requester_id' => User::factory()->create()->id, 'subject' => 'Hi', 'body' => '<p>Hi</p>'])->assertForbidden();

    expect(TicketMessage::query()->sole()->is_internal)->toBeTrue();
});
