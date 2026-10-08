<?php

use App\Domain\Accounts\Enums\AssignmentMode;
use App\Domain\Accounts\Models\Group;
use App\Domain\Tickets\Actions\CreateTicket;
use App\Domain\Tickets\Actions\UpdateTicket;
use App\Domain\Tickets\Enums\TicketChannel;
use App\Domain\Tickets\Enums\TicketStatus;
use App\Domain\Tickets\Models\Ticket;
use App\Domain\Tickets\Notifications\TicketAssigned;
use App\Models\User;
use Illuminate\Support\Facades\Notification;

/**
 * @param  array<string, mixed>  $attributes
 */
function ticketForGroup(Group $group, array $attributes = []): Ticket
{
    return app(CreateTicket::class)->handle(
        User::factory()->create(),
        ['subject' => 'Help', 'body' => '<p>Hi</p>', 'group_id' => $group->id, ...$attributes],
        TicketChannel::Portal,
    );
}

test('round robin rotates through available full agents, skipping light agents and people away', function () {
    $group = Group::factory()->create(['assignment_mode' => AssignmentMode::RoundRobin]);
    [$ana, $bo] = User::factory()->agent()->count(2)->create()->all();
    $away = User::factory()->agent()->create(['is_available' => false]);
    $light = User::factory()->lightAgent()->create();
    $group->agents()->attach([$ana->id, $bo->id, $away->id, $light->id]);

    $assignees = collect(range(1, 3))->map(fn () => ticketForGroup($group)->assignee_id)->all();

    expect($assignees)->toBe([$ana->id, $bo->id, $ana->id]);
    expect(Ticket::query()->first()?->status)->toBe(TicketStatus::Open);
});

test('least busy picks the agent with the fewest unresolved tickets', function () {
    $group = Group::factory()->create(['assignment_mode' => AssignmentMode::LeastBusy]);
    [$busy, $free] = User::factory()->agent()->count(2)->create()->all();
    $group->agents()->attach([$busy->id, $free->id]);
    Ticket::factory()->count(2)->assignedTo($busy)->create();
    Ticket::factory()->count(3)->assignedTo($free)->status(TicketStatus::Solved)->create();

    expect(ticketForGroup($group)->assignee_id)->toBe($free->id);
});

test('manual groups, explicit assignees and empty groups are left alone', function () {
    $manual = Group::factory()->create();
    $agent = User::factory()->agent()->create();
    $manual->agents()->attach($agent);
    $roundRobin = Group::factory()->create(['assignment_mode' => AssignmentMode::RoundRobin]);
    $roundRobin->agents()->attach($agent);
    $chosen = User::factory()->agent()->create();

    expect(ticketForGroup($manual)->assignee_id)->toBeNull()
        ->and(ticketForGroup($roundRobin, ['assignee_id' => $chosen->id])->assignee_id)->toBe($chosen->id)
        ->and(ticketForGroup(Group::factory()->create(['assignment_mode' => AssignmentMode::RoundRobin]))->assignee_id)->toBeNull();
});

test('moving an unassigned ticket to an auto-assigning group assigns it and notifies the agent', function () {
    Notification::fake();
    $group = Group::factory()->create(['assignment_mode' => AssignmentMode::RoundRobin]);
    $agent = User::factory()->agent()->create();
    $group->agents()->attach($agent);
    $ticket = Ticket::factory()->create();

    app(UpdateTicket::class)->handle($ticket, ['group_id' => $group->id], User::factory()->admin()->create());

    expect($ticket->refresh()->assignee_id)->toBe($agent->id);
    Notification::assertSentTo($agent, TicketAssigned::class);
});

test('auto-assigned new tickets notify the agent', function () {
    Notification::fake();
    $group = Group::factory()->create(['assignment_mode' => AssignmentMode::RoundRobin]);
    $agent = User::factory()->agent()->create();
    $group->agents()->attach($agent);

    ticketForGroup($group);

    Notification::assertSentTo($agent, TicketAssigned::class);
});

test('agents can mark themselves away and admins set a group assignment mode', function () {
    $agent = User::factory()->agent()->create();
    $this->actingAs($agent)->patch(route('agent.availability'));
    expect($agent->refresh()->is_available)->toBeFalse();

    $group = Group::factory()->create();
    $this->actingAs(User::factory()->admin()->create())
        ->put(route('admin.groups.update', $group), ['name' => $group->name, 'assignment_mode' => 'least_busy'])
        ->assertSessionHasNoErrors();
    expect($group->refresh()->assignment_mode)->toBe(AssignmentMode::LeastBusy);
});
