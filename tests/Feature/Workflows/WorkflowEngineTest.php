<?php

use App\Domain\Tickets\Actions\AddMessage;
use App\Domain\Tickets\Actions\UpdateTicket;
use App\Domain\Tickets\Enums\TicketChannel;
use App\Domain\Tickets\Enums\TicketPriority;
use App\Domain\Tickets\Enums\TicketStatus;
use App\Domain\Tickets\Models\Ticket;
use App\Domain\Workflows\Enums\RunStatus;
use App\Domain\Workflows\Enums\WorkflowTrigger;
use App\Domain\Workflows\Models\Workflow;
use App\Domain\Workflows\Models\WorkflowRun;
use App\Models\User;

require_once __DIR__.'/helpers.php';

test('a ticket created trigger runs its actions and records each step', function () {
    $workflow = Workflow::factory()->chain(WorkflowTrigger::TicketCreated, [
        ['update_ticket', ['priority' => 'high']],
        ['add_tags', ['tags' => ['triaged']]],
        ['add_note', ['body' => '<p>Hello {{requester.first_name}} from {{workflow.name}}</p>']],
    ])->create(['name' => 'Triage']);

    $ticket = workflowTicket(requester: User::factory()->create(['name' => 'Ana Lima']))->refresh();

    $run = WorkflowRun::query()->sole();
    $note = $ticket->messages()->where('is_internal', true)->sole();

    expect($run->status)->toBe(RunStatus::Completed)
        ->and($run->steps()->pluck('node_id')->all())->toBe(['trigger', 's1', 's2', 's3'])
        ->and($ticket->priority)->toBe(TicketPriority::High)
        ->and($ticket->tags()->pluck('name')->all())->toBe(['triaged'])
        ->and($note->author_id)->toBeNull()
        ->and($note->body)->toContain('Hello Ana from Triage')
        ->and($note->metadata)->toBe(['workflow' => ['id' => $workflow->id, 'name' => 'Triage']])
        ->and($workflow->refresh()->last_run_at)->not->toBeNull();
});

test('if nodes follow the branch that matches', function (string $subject, string $tag) {
    Workflow::factory()->flow(WorkflowTrigger::TicketCreated, [
        'check' => ['if', ['conditions' => workflowCondition('subject', 'contains', 'refund')]],
        'yes' => ['add_tags', ['tags' => ['billing']]],
        'no' => ['add_tags', ['tags' => ['general']]],
    ], [['trigger', 'out', 'check'], ['check', 'true', 'yes'], ['check', 'false', 'no']])->create();

    $ticket = workflowTicket(['subject' => $subject]);

    expect($ticket->tags()->pluck('name')->all())->toBe([$tag]);
})->with([
    'matching' => ['I want a REFUND', 'billing'],
    'not matching' => ['Printer on fire', 'general'],
]);

test('switch nodes pick the matching case or the default', function (TicketPriority $priority, string $tag) {
    Workflow::factory()->flow(WorkflowTrigger::TicketCreated, [
        'route' => ['switch', ['field' => 'priority', 'cases' => [['id' => 'c1', 'value' => 'urgent'], ['id' => 'c2', 'value' => 'high']]]],
        'urgent' => ['add_tags', ['tags' => ['page_on_call']]],
        'high' => ['add_tags', ['tags' => ['today']]],
        'other' => ['add_tags', ['tags' => ['queue']]],
    ], [['trigger', 'out', 'route'], ['route', 'c1', 'urgent'], ['route', 'c2', 'high'], ['route', 'default', 'other']])->create();

    expect(workflowTicket(['priority' => $priority])->tags()->pluck('name')->all())->toBe([$tag]);
})->with([
    [TicketPriority::Urgent, 'page_on_call'],
    [TicketPriority::High, 'today'],
    [TicketPriority::Low, 'queue'],
]);

test('for each runs its body per item, filters skip items, and actions can target the item', function () {
    $requester = User::factory()->create();
    $pending = Ticket::factory()->status(TicketStatus::Pending)->create(['requester_id' => $requester->id]);
    $open = Ticket::factory()->status(TicketStatus::Open)->create(['requester_id' => $requester->id]);

    Workflow::factory()->flow(WorkflowTrigger::TicketCreated, [
        'each' => ['for_each', ['collection' => 'requester_tickets']],
        'only_pending' => ['filter', ['conditions' => workflowCondition('item.status', 'is', 'pending')]],
        'solve' => ['update_ticket', ['apply_to' => 'item', 'status' => 'solved']],
        'count' => ['set_variable', ['name' => 'solved', 'value' => '{{loop.index}}', 'kind' => 'number']],
        'done' => ['add_note', ['body' => '<p>Checked {{vars.solved}} earlier request(s).</p>']],
    ], [
        ['trigger', 'out', 'each'], ['each', 'each', 'only_pending'], ['only_pending', 'out', 'solve'],
        ['solve', 'out', 'count'], ['each', 'done', 'done'],
    ])->create();

    $ticket = workflowTicket(requester: $requester);

    expect($pending->refresh()->status)->toBe(TicketStatus::Solved)
        ->and($open->refresh()->status)->toBe(TicketStatus::Open)
        ->and($ticket->refresh()->status)->toBe(TicketStatus::New)
        ->and($ticket->messages()->where('is_internal', true)->sole()->body)->toContain('Checked 1 earlier request(s).');
});

test('a wait pauses the run and workflows:resume continues it with its variables', function () {
    Workflow::factory()->chain(WorkflowTrigger::TicketCreated, [
        ['set_variable', ['name' => 'greeting', 'value' => 'Still there?', 'kind' => 'text']],
        ['wait', ['amount' => 2, 'unit' => 'hours']],
        ['add_note', ['body' => '<p>{{vars.greeting}}</p>']],
    ])->create();

    $ticket = workflowTicket();
    $run = WorkflowRun::query()->sole();

    expect($run->status)->toBe(RunStatus::Waiting)
        ->and($run->current_node_id)->toBe('s2')
        ->and($ticket->messages()->where('is_internal', true)->exists())->toBeFalse();

    $this->travel(1)->hours();
    $this->artisan('workflows:resume')->assertSuccessful();
    expect($run->refresh()->status)->toBe(RunStatus::Waiting);

    $this->travel(2)->hours();
    $this->artisan('workflows:resume')->assertSuccessful();

    expect($run->refresh()->status)->toBe(RunStatus::Completed)
        ->and($ticket->messages()->where('is_internal', true)->sole()->body)->toContain('Still there?');
});

test('a waiting run keeps the graph it started with when the workflow is edited', function () {
    $workflow = Workflow::factory()->chain(WorkflowTrigger::TicketCreated, [
        ['wait', ['amount' => 1, 'unit' => 'hours']],
        ['add_tags', ['tags' => ['original']]],
    ])->create();
    $ticket = workflowTicket();

    $workflow->update(['graph' => ['nodes' => [], 'edges' => []]]);
    $this->travel(2)->hours();
    $this->artisan('workflows:resume');

    expect($ticket->tags()->pluck('name')->all())->toBe(['original']);
});

test('wait for reply continues from replied when the customer answers, else from timeout', function (bool $customerReplies, string $tag) {
    Workflow::factory()->flow(WorkflowTrigger::TicketCreated, [
        'wait' => ['wait_for_reply', ['amount' => 1, 'unit' => 'days']],
        'replied' => ['add_tags', ['tags' => ['answered']]],
        'timeout' => ['add_tags', ['tags' => ['no_answer']]],
    ], [['trigger', 'out', 'wait'], ['wait', 'replied', 'replied'], ['wait', 'timeout', 'timeout']])->create();

    $ticket = workflowTicket();

    if ($customerReplies) {
        app(AddMessage::class)->handle($ticket, $ticket->requester, '<p>Here you go</p>', channel: TicketChannel::Portal);
    } else {
        $this->travel(25)->hours();
        $this->artisan('workflows:resume');
    }

    expect($ticket->tags()->pluck('name')->all())->toBe([$tag]);
})->with([
    'reply' => [true, 'answered'],
    'timeout' => [false, 'no_answer'],
]);

test('ticket updated triggers only fire for the watched fields, and a workflow does not retrigger itself', function () {
    Workflow::factory()->chain(WorkflowTrigger::TicketUpdated, [
        ['update_ticket', ['priority' => 'urgent']],
    ], ['fields' => ['status']])->create(['max_runs_per_ticket' => 0]);
    $ticket = workflowTicket();

    app(UpdateTicket::class)->handle($ticket, ['subject' => 'Renamed']);
    expect(WorkflowRun::query()->count())->toBe(0);

    app(UpdateTicket::class)->handle($ticket, ['status' => TicketStatus::Pending]);

    expect(WorkflowRun::query()->count())->toBe(1)
        ->and($ticket->refresh()->priority)->toBe(TicketPriority::Urgent);
});

test('workflows triggering each other stop after a few levels', function () {
    Workflow::factory()->count(5)->chain(WorkflowTrigger::TicketUpdated, [
        ['add_note', ['body' => '<p>ping</p>']],
        ['update_ticket', ['subject' => null, 'custom_fields' => ['pings' => '{{ticket.id}}-{{workflow.name}}']]],
    ])->create(['max_runs_per_ticket' => 0]);
    $ticket = workflowTicket();

    app(UpdateTicket::class)->handle($ticket, ['status' => TicketStatus::Open]);

    expect(WorkflowRun::query()->max('depth'))->toBe(2);
});

test('each workflow runs at most its run limit per ticket', function () {
    Workflow::factory()->chain(WorkflowTrigger::CustomerReplied, [['add_tags', ['tags' => ['replied']]]])->create(['max_runs_per_ticket' => 2]);
    $ticket = workflowTicket();

    foreach (range(1, 3) as $reply) {
        app(AddMessage::class)->handle($ticket, $ticket->requester, "<p>Reply {$reply}</p>", channel: TicketChannel::Portal);
    }

    expect(WorkflowRun::query()->count())->toBe(2);
});

test('a failing action fails the run and the history keeps the error', function () {
    Workflow::factory()->chain(WorkflowTrigger::TicketCreated, [
        ['update_ticket', ['apply_to' => 'item', 'status' => 'solved']],
    ])->create();

    workflowTicket();
    $run = WorkflowRun::query()->sole();

    expect($run->status)->toBe(RunStatus::Failed)
        ->and($run->error)->toBe('The current loop item is not a ticket.')
        ->and($run->steps()->where('status', 'failed')->sole()->error)->toBe('The current loop item is not a ticket.');
});

test('inactive workflows and other triggers do not run', function () {
    Workflow::factory()->chain(WorkflowTrigger::TicketCreated, [['add_tags', ['tags' => ['x']]]])->create(['is_active' => false]);
    Workflow::factory()->chain(WorkflowTrigger::CustomerReplied, [['add_tags', ['tags' => ['y']]]])->create();

    expect(workflowTicket()->tags()->count())->toBe(0)
        ->and(WorkflowRun::query()->count())->toBe(0);
});
