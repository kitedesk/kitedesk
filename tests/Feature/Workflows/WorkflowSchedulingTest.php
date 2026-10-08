<?php

use App\Domain\Tickets\Actions\AddMessage;
use App\Domain\Tickets\Enums\TicketStatus;
use App\Domain\Tickets\Models\Ticket;
use App\Domain\Workflows\Enums\WorkflowTrigger;
use App\Domain\Workflows\Models\Workflow;
use App\Domain\Workflows\Models\WorkflowRun;
use App\Models\User;

/**
 * @param  array<string, mixed>  $settings
 */
function idleWorkflow(array $settings = [], array $attributes = []): Workflow
{
    return Workflow::factory()->chain(WorkflowTrigger::TicketIdle, [
        ['update_ticket', ['status' => 'solved']],
    ], ['anchor' => 'agent_reply', 'amount' => 3, 'unit' => 'days', 'statuses' => ['pending'], ...$settings])->create($attributes);
}

test('time-based workflows act on tickets idle past the threshold, once per idle period', function () {
    $this->freezeSecond();
    $workflow = idleWorkflow(attributes: ['apply_to_existing' => true]);
    $idle = Ticket::factory()->status(TicketStatus::Pending)->create(['last_agent_reply_at' => now()->subDays(4)]);
    $recent = Ticket::factory()->status(TicketStatus::Pending)->create(['last_agent_reply_at' => now()->subDays(2)]);
    $open = Ticket::factory()->status(TicketStatus::Open)->create(['last_agent_reply_at' => now()->subDays(4)]);

    $this->artisan('workflows:scan')->assertSuccessful();
    $this->artisan('workflows:scan')->assertSuccessful();

    expect($idle->refresh()->status)->toBe(TicketStatus::Solved)
        ->and($recent->refresh()->status)->toBe(TicketStatus::Pending)
        ->and($open->refresh()->status)->toBe(TicketStatus::Open)
        ->and($workflow->runs()->count())->toBe(1);
});

test('time-based workflows skip tickets created before activation unless asked to apply to them', function () {
    $old = Ticket::factory()->status(TicketStatus::Pending)->create(['last_agent_reply_at' => now()->subDays(10), 'created_at' => now()->subDays(10)]);
    idleWorkflow();

    $this->artisan('workflows:scan');

    expect($old->refresh()->status)->toBe(TicketStatus::Pending);
});

test('a new idle period after activity starts the workflow again, up to the run limit', function () {
    $workflow = Workflow::factory()->chain(WorkflowTrigger::TicketIdle, [
        ['add_note', ['body' => '<p>Nudge</p>']],
    ], ['anchor' => 'agent_reply', 'amount' => 1, 'unit' => 'days', 'statuses' => ['pending']])->create(['apply_to_existing' => true, 'max_runs_per_ticket' => 2]);
    $ticket = Ticket::factory()->status(TicketStatus::Pending)->create(['last_agent_reply_at' => now()->subDays(2)]);
    $agent = User::factory()->agent()->create();

    foreach (range(1, 3) as $round) {
        $this->artisan('workflows:scan');
        app(AddMessage::class)->handle($ticket->refresh(), $agent, '<p>Any news?</p>', statusAfter: TicketStatus::Pending);
        $this->travel(2)->days();
    }

    expect($workflow->runs()->count())->toBe(2);
});

test('sla breaches start sla workflows', function () {
    Workflow::factory()->chain(WorkflowTrigger::SlaBreached, [['add_tags', ['tags' => ['escalated']]]])->create();
    $ticket = Ticket::factory()->create(['first_response_due_at' => now()->subMinute()]);

    $this->artisan('sla:check-breaches');

    expect($ticket->tags()->pluck('name')->all())->toBe(['escalated'])
        ->and(WorkflowRun::query()->sole()->trigger_event)->toBe('sla_breached');
});
