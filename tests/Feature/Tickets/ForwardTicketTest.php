<?php

use App\Domain\Accounts\Enums\AssignmentMode;
use App\Domain\Accounts\Models\Group;
use App\Domain\Tickets\Models\Ticket;
use App\Domain\Tickets\Notifications\TicketAssigned;
use App\Domain\Tickets\Notifications\TicketForwarded;
use App\Models\User;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();

    $this->agent = User::factory()->agent()->create();
    $this->ticket = Ticket::factory()->assignedTo($this->agent)->create();
});

test('forwarding to an agent assigns it, adds the note and notifies only them', function () {
    $colleague = User::factory()->agent()->create();

    $this->actingAs($this->agent)
        ->post(route('agent.tickets.forward', $this->ticket), [
            'assignee_id' => $colleague->id,
            'note' => '<p>Customer is on the enterprise plan.</p>',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($this->ticket->fresh()->assignee_id)->toBe($colleague->id);

    $note = $this->ticket->messages()->sole();
    expect($note->is_internal)->toBeTrue()
        ->and($note->author_id)->toBe($this->agent->id)
        ->and($note->metadata['forwarded_to'])->toBe(['type' => 'agent', 'id' => $colleague->id, 'name' => $colleague->name]);

    Notification::assertSentTo($colleague, TicketForwarded::class, function (TicketForwarded $notification) use ($colleague) {
        $data = $notification->toArray($colleague);

        return $notification->via($colleague) === ['mail', 'database']
            && $data['kind'] === 'ticket_forwarded'
            && $data['group'] === null
            && $data['excerpt'] === 'Customer is on the enterprise plan.';
    });
    Notification::assertNotSentTo($colleague, TicketAssigned::class);
    Notification::assertNotSentTo($this->agent, TicketForwarded::class);
});

test('forwarding to a group unassigns it and notifies every member but the sender', function () {
    $group = Group::factory()->create(['name' => 'Billing']);
    $members = User::factory()->agent()->count(2)->create();
    $light = User::factory()->lightAgent()->create();
    $group->agents()->attach([...$members->modelKeys(), $light->id, $this->agent->id]);

    $this->actingAs($this->agent)
        ->post(route('agent.tickets.forward', $this->ticket), ['group_id' => $group->id])
        ->assertSessionHasNoErrors();

    $ticket = $this->ticket->fresh();
    expect($ticket->group_id)->toBe($group->id)
        ->and($ticket->assignee_id)->toBeNull()
        ->and($ticket->messages()->count())->toBe(0);

    Notification::assertSentTo([...$members, $light], TicketForwarded::class, fn (TicketForwarded $notification) => $notification->group === 'Billing');
    Notification::assertNotSentTo($this->agent, TicketForwarded::class);
});

test('a group that assigns automatically notifies only the agent it picked', function () {
    $group = Group::factory()->create(['assignment_mode' => AssignmentMode::RoundRobin]);
    [$picked, $other] = User::factory()->agent()->count(2)->create()->all();
    $group->agents()->attach([$picked->id, $other->id]);

    $this->actingAs($this->agent)
        ->post(route('agent.tickets.forward', $this->ticket), ['group_id' => $group->id])
        ->assertSessionHasNoErrors();

    expect($this->ticket->fresh()->assignee_id)->toBe($picked->id);
    Notification::assertSentTo($picked, TicketForwarded::class);
    Notification::assertNotSentTo($other, TicketForwarded::class);
    Notification::assertNotSentTo($picked, TicketAssigned::class);
});

test('forwarding needs a valid target other than the current one', function (Closure $payload, string $field) {
    $this->actingAs($this->agent)
        ->post(route('agent.tickets.forward', $this->ticket), $payload($this))
        ->assertSessionHasErrors($field);

    expect($this->ticket->fresh()->assignee_id)->toBe($this->agent->id);
    Notification::assertNothingSent();
})->with([
    'nobody' => [fn () => [], 'assignee_id'],
    'yourself (the current assignee)' => [fn ($test) => ['assignee_id' => $test->agent->id], 'assignee_id'],
    'a customer' => [fn () => ['assignee_id' => User::factory()->create()->id], 'assignee_id'],
    'a light agent' => [fn () => ['assignee_id' => User::factory()->lightAgent()->create()->id], 'assignee_id'],
    'an agent and a group' => [fn () => ['assignee_id' => User::factory()->agent()->create()->id, 'group_id' => Group::factory()->create()->id], 'assignee_id'],
]);

test('an unassigned ticket cannot be forwarded to the group it is already waiting in', function () {
    $group = Group::factory()->create();
    $ticket = Ticket::factory()->create(['group_id' => $group->id, 'assignee_id' => null]);

    $this->actingAs($this->agent)
        ->post(route('agent.tickets.forward', $ticket), ['group_id' => $group->id])
        ->assertSessionHasErrors('group_id');
});

test('light agents cannot forward tickets', function () {
    $this->actingAs(User::factory()->lightAgent()->create())
        ->post(route('agent.tickets.forward', $this->ticket), ['assignee_id' => User::factory()->agent()->create()->id])
        ->assertForbidden();
});
