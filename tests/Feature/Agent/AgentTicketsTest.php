<?php

use App\Domain\Tickets\Enums\TicketStatus;
use App\Domain\Tickets\Models\Ticket;
use App\Domain\Tickets\Models\TicketMessage;
use App\Domain\Tickets\Support\TicketThread;
use App\Domain\Tickets\Support\TicketViews;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->agent = User::factory()->agent()->create();
});

test('customers cannot open the agent workspace', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('agent.tickets.index'))
        ->assertForbidden();
});

test('the default view lists unassigned unresolved tickets', function () {
    $unassigned = Ticket::factory()->create();
    Ticket::factory()->assignedTo($this->agent)->create();
    Ticket::factory()->status(TicketStatus::Solved)->create();

    $this->actingAs($this->agent)
        ->get(route('agent.tickets.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('agent/tickets/index')
            ->where('view', 'unassigned')
            ->has('tickets.data', 1)
            ->where('tickets.data.0.id', $unassigned->id)
            ->where('agentNav.views.0.key', 'mine'));
});

test('the mine view lists tickets assigned to the current agent', function () {
    $mine = Ticket::factory()->assignedTo($this->agent)->create();
    Ticket::factory()->assignedTo(User::factory()->agent()->create())->create();

    $this->actingAs($this->agent)
        ->get(route('agent.tickets.index', ['view' => 'mine']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('tickets.data', 1)
            ->where('tickets.data.0.id', $mine->id));
});

test('tickets can be filtered by subject or id', function () {
    $match = Ticket::factory()->create(['subject' => 'Refund for order 42']);
    Ticket::factory()->create(['subject' => 'Login problem']);

    $this->actingAs($this->agent)
        ->get(route('agent.tickets.index', ['filter' => ['search' => 'refund']]))
        ->assertInertia(fn (Assert $page) => $page->has('tickets.data', 1)->where('tickets.data.0.id', $match->id));

    $this->actingAs($this->agent)
        ->get(route('agent.tickets.index', ['filter' => ['search' => '#'.$match->id]]))
        ->assertInertia(fn (Assert $page) => $page->has('tickets.data', 1));
});

test('agents see internal notes on the ticket page', function () {
    $ticket = Ticket::factory()->create();
    TicketMessage::factory()->for($ticket)->create();
    TicketMessage::factory()->for($ticket)->internal()->create();

    $this->actingAs($this->agent)
        ->get(route('agent.tickets.show', $ticket))
        ->assertInertia(fn (Assert $page) => $page
            ->component('agent/tickets/show')
            ->has('messages', 2)
            ->where('can.reply', true));
});

test('agents can change ticket properties', function () {
    $ticket = Ticket::factory()->create();

    $this->actingAs($this->agent)
        ->patch(route('agent.tickets.update', $ticket), ['assignee_id' => $this->agent->id, 'priority' => 'high', 'tags' => ['VIP']])
        ->assertRedirect();

    $ticket->refresh();
    expect($ticket->assignee_id)->toBe($this->agent->id)
        ->and($ticket->priority->value)->toBe('high')
        ->and($ticket->status)->toBe(TicketStatus::Open)
        ->and($ticket->tags->pluck('name')->all())->toBe(['vip']);
});

test('customers cannot be assigned tickets', function () {
    $ticket = Ticket::factory()->create();

    $this->actingAs($this->agent)
        ->patch(route('agent.tickets.update', $ticket), ['assignee_id' => User::factory()->create()->id])
        ->assertSessionHasErrors('assignee_id');
});

test('light agents can add internal notes but cannot reply publicly or edit properties', function () {
    $lightAgent = User::factory()->lightAgent()->create();
    $ticket = Ticket::factory()->create();

    $this->actingAs($lightAgent)
        ->post(route('agent.tickets.messages.store', $ticket), ['body' => '<p>FYI</p>', 'is_internal' => true])
        ->assertRedirect();

    $this->actingAs($lightAgent)
        ->post(route('agent.tickets.messages.store', $ticket), ['body' => '<p>Hello</p>'])
        ->assertForbidden();

    $this->actingAs($lightAgent)
        ->patch(route('agent.tickets.update', $ticket), ['priority' => 'urgent'])
        ->assertForbidden();

    $this->actingAs($lightAgent)
        ->post(route('agent.tickets.messages.store', $ticket), ['body' => '<p>Done</p>', 'is_internal' => true, 'status' => 'solved'])
        ->assertSessionHasErrors('status');

    $this->actingAs($lightAgent)
        ->post(route('agent.tickets.store'), ['requester_email' => 'x@example.com', 'requester_name' => 'X', 'subject' => 'Hi', 'body' => '<p>Hi</p>'])
        ->assertForbidden();

    expect($ticket->messages()->sole()->is_internal)->toBeTrue()
        ->and($ticket->refresh()->status)->not->toBe(TicketStatus::Solved)
        ->and(Ticket::query()->count())->toBe(1);
});

test('agents can reply and choose the resulting status', function () {
    $ticket = Ticket::factory()->create();

    $this->actingAs($this->agent)
        ->post(route('agent.tickets.messages.store', $ticket), ['body' => '<p>Could you send a screenshot?</p>', 'status' => 'pending'])
        ->assertRedirect();

    expect($ticket->refresh()->status)->toBe(TicketStatus::Pending)
        ->and($ticket->first_responded_at)->not->toBeNull();
});

test('empty replies are rejected', function () {
    $ticket = Ticket::factory()->create();

    $this->actingAs($this->agent)
        ->post(route('agent.tickets.messages.store', $ticket), ['body' => '<p> </p><script>x</script>'])
        ->assertSessionHasErrors('body');
});

test('agents can update tickets in bulk', function () {
    $tickets = Ticket::factory()->count(3)->create();

    $this->actingAs($this->agent)
        ->patch(route('agent.tickets.bulk'), ['ids' => $tickets->pluck('id')->all(), 'status' => 'solved'])
        ->assertRedirect();

    expect(Ticket::query()->where('status', TicketStatus::Solved)->count())->toBe(3);
});

test('agents can open a ticket on behalf of a new customer', function () {
    $this->actingAs($this->agent)
        ->post(route('agent.tickets.store'), [
            'requester_name' => 'Ada Lovelace',
            'requester_email' => 'ADA@example.com',
            'subject' => 'Phone call: invoice question',
            'body' => '<p>Customer called about invoice #77.</p>',
            'priority' => 'high',
        ])
        ->assertRedirect();

    $ticket = Ticket::query()->sole();
    expect($ticket->requester->email)->toBe('ada@example.com')
        ->and($ticket->requester->isCustomer())->toBeTrue()
        ->and($ticket->messages()->sole()->author_id)->toBe($this->agent->id);
});

test('quick search finds tickets and people', function () {
    $ticket = Ticket::factory()->create(['subject' => 'Billing address change']);
    User::factory()->create(['name' => 'Billie Jean']);

    $this->actingAs($this->agent)
        ->getJson(route('agent.search', ['q' => 'bill']))
        ->assertOk()
        ->assertJsonPath('tickets.0.id', $ticket->id)
        ->assertJsonPath('users.0.name', 'Billie Jean');
});

test('the ticket page shows who wrote each message and who the ticket belongs to', function () {
    $requester = User::factory()->create(['name' => 'Casey']);
    $ticket = Ticket::factory()->for($requester, 'requester')->assignedTo($this->agent)->create();
    TicketMessage::factory()->for($ticket)->create(['author_id' => $this->agent->id, 'is_internal' => false]);

    $this->actingAs($this->agent)
        ->get(route('agent.tickets.show', $ticket))
        ->assertInertia(fn (Assert $page) => $page
            ->where('messages.0.author.name', $this->agent->name)
            ->where('ticket.requester.name', 'Casey')
            ->where('ticket.assignee.id', $this->agent->id));
});

test('sidebar counts are shared between agents and refresh when a ticket changes', function () {
    Ticket::factory()->create();
    $count = fn (): int => collect(TicketViews::summary($this->agent))->firstWhere('key', 'unassigned')['count'];

    expect($count())->toBe(1);

    $ticket = Ticket::factory()->create();
    expect($count())->toBe(2);

    $ticket->forceFill(['assignee_id' => $this->agent->id])->save();
    expect($count())->toBe(1);
});

test('long conversations show the latest messages until the whole thread is asked for', function () {
    $ticket = Ticket::factory()->create();
    TicketMessage::factory()->for($ticket)->count(TicketThread::RECENT + 3)->sequence(fn ($sequence) => ['created_at' => now()->subMinutes(100 - $sequence->index)])->create();
    $last = $ticket->messages()->latest('id')->first();

    $this->actingAs($this->agent)
        ->get(route('agent.tickets.show', $ticket))
        ->assertInertia(fn (Assert $page) => $page
            ->has('messages', TicketThread::RECENT)
            ->where('messages.'.(TicketThread::RECENT - 1).'.id', $last->id)
            ->where('earlierMessages', 3));

    $this->actingAs($this->agent)
        ->get(route('agent.tickets.show', [$ticket, 'thread_all' => 1]))
        ->assertInertia(fn (Assert $page) => $page->has('messages', TicketThread::RECENT + 3)->where('earlierMessages', 0));
});

test('searching with a comma finds the phrase instead of failing', function () {
    $ticket = Ticket::factory()->create(['subject' => 'Refund, please']);
    Ticket::factory()->create(['subject' => 'Other']);

    $this->actingAs($this->agent)
        ->get(route('agent.tickets.index', ['view' => 'all', 'filter' => ['search' => 'Refund, please']]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('tickets.data', 1)->where('tickets.data.0.id', $ticket->id));
});
