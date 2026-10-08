<?php

use App\Domain\Accounts\Models\Group;
use App\Domain\Tickets\Actions\CreateTicket;
use App\Domain\Tickets\Enums\TicketChannel;
use App\Domain\Tickets\Enums\TicketStatus;
use App\Domain\Tickets\Models\Ticket;
use App\Domain\Workflows\Enums\RunStatus;
use App\Domain\Workflows\Enums\WorkflowTrigger;
use App\Domain\Workflows\Models\Workflow;
use App\Domain\Workflows\Models\WorkflowRun;
use App\Models\User;
use Database\Factories\WorkflowFactory;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
});

/**
 * @return array<string, mixed>
 */
function workflowPayload(array $graph, array $overrides = []): array
{
    return ['name' => 'Close stale', 'description' => null, 'is_active' => true, 'max_runs_per_ticket' => 1, 'apply_to_existing' => false, 'graph' => $graph, ...$overrides];
}

test('admins create workflows; the trigger comes from the graph and editor state is dropped', function () {
    $graph = WorkflowFactory::graph(WorkflowTrigger::TicketIdle, ['solve' => ['update_ticket', ['status' => 'solved']]], [['trigger', 'out', 'solve']], ['anchor' => 'agent_reply', 'amount' => 3, 'unit' => 'days']);
    $graph['nodes'][1]['selected'] = true;

    $this->actingAs($this->admin)
        ->post(route('admin.workflows.store'), workflowPayload($graph))
        ->assertRedirect(route('admin.workflows.edit', Workflow::query()->sole()));

    $workflow = Workflow::query()->sole();

    expect($workflow->trigger)->toBe(WorkflowTrigger::TicketIdle)
        ->and($workflow->is_active)->toBeTrue()
        ->and($workflow->activated_at)->not->toBeNull()
        ->and($workflow->created_by)->toBe($this->admin->id)
        ->and($workflow->graph['nodes'][1])->not->toHaveKey('selected');
});

test('graph errors come back per node', function () {
    $graph = WorkflowFactory::graph(WorkflowTrigger::TicketCreated, ['a' => ['update_ticket', ['status' => 'exploded']]], [['trigger', 'out', 'a']]);

    $this->actingAs($this->admin)
        ->post(route('admin.workflows.store'), workflowPayload($graph))
        ->assertSessionHasErrors(['nodes.a']);

    expect(Workflow::query()->count())->toBe(0);
});

test('only admins manage workflows', function () {
    $this->actingAs(User::factory()->agent()->create())
        ->get(route('admin.workflows.index'))
        ->assertForbidden();
});

test('the editor lists runs and shows the steps of the selected one', function () {
    $workflow = Workflow::factory()->chain(WorkflowTrigger::TicketCreated, [['add_tags', ['tags' => ['x']]]])->create();
    workflowTicketForAdmin();
    $run = WorkflowRun::query()->sole();

    $this->actingAs($this->admin)
        ->get(route('admin.workflows.edit', ['workflow' => $workflow, 'run' => $run->id]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('admin/workflows/editor')
            ->has('runs', 1)
            ->where('selectedRun.id', $run->id)
            ->has('selectedRun.steps', 2));
});

test('testing a graph shows the path without changing the ticket', function () {
    $ticket = Ticket::factory()->create(['subject' => 'Refund please']);
    $graph = WorkflowFactory::graph(WorkflowTrigger::TicketCreated, [
        'check' => ['if', ['conditions' => ['match' => 'all', 'conditions' => [['field' => 'subject', 'operator' => 'contains', 'value' => 'refund']]]]],
        'solve' => ['update_ticket', ['status' => 'solved']],
    ], [['trigger', 'out', 'check'], ['check', 'true', 'solve']]);

    $this->actingAs($this->admin)
        ->postJson(route('admin.workflows.test'), ['graph' => $graph, 'ticket_id' => $ticket->id])
        ->assertOk()
        ->assertJsonPath('status', 'completed')
        ->assertJsonPath('steps.1.handle', 'true')
        ->assertJsonPath('steps.2.output.changes.status', 'solved');

    expect($ticket->refresh()->status)->toBe(TicketStatus::New)
        ->and(WorkflowRun::query()->count())->toBe(0);
});

test('waiting runs can be cancelled', function () {
    $workflow = Workflow::factory()->chain(WorkflowTrigger::TicketCreated, [['wait', ['amount' => 1, 'unit' => 'days']]])->create();
    workflowTicketForAdmin();
    $run = WorkflowRun::query()->sole();

    $this->actingAs($this->admin)->post(route('admin.workflows.runs.cancel', [$workflow, $run]))->assertRedirect();

    expect($run->refresh()->status)->toBe(RunStatus::Cancelled);
});

test('deleting a group turns off the workflows that use it', function () {
    $group = Group::factory()->create(['name' => 'Billing']);
    $workflow = Workflow::factory()->chain(WorkflowTrigger::TicketCreated, [['update_ticket', ['group_id' => $group->id]]])->create();
    $unrelated = Workflow::factory()->create();

    $group->delete();

    expect($workflow->refresh()->is_active)->toBeFalse()
        ->and($workflow->disabled_reason)->toContain('Billing')
        ->and($unrelated->refresh()->is_active)->toBeTrue();
});

test('agents run manual workflows on tickets they can edit', function () {
    $agent = User::factory()->agent()->create();
    $ticket = Ticket::factory()->create();
    $workflow = Workflow::factory()->chain(WorkflowTrigger::Manual, [['add_tags', ['tags' => ['vip']]]])->create();
    $adminsOnly = Workflow::factory()->chain(WorkflowTrigger::Manual, [['add_tags', ['tags' => ['secret']]]], ['run_by' => 'admins'])->create();

    $this->actingAs($agent)
        ->get(route('agent.tickets.show', $ticket))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('workflows.manual', [['id' => $workflow->id, 'name' => $workflow->name]]));

    $this->actingAs($agent)->post(route('agent.tickets.workflows.store', [$ticket, $workflow]))->assertRedirect();
    $this->actingAs($agent)->post(route('agent.tickets.workflows.store', [$ticket, $adminsOnly]))->assertForbidden();
    $this->actingAs(User::factory()->lightAgent()->create())->post(route('agent.tickets.workflows.store', [$ticket, $workflow]))->assertForbidden();

    expect($ticket->tags()->pluck('name')->all())->toBe(['vip'])
        ->and(WorkflowRun::query()->sole()->started_by)->toBe($agent->id);
});

function workflowTicketForAdmin(): Ticket
{
    return app(CreateTicket::class)->handle(User::factory()->create(), ['subject' => 'Hi', 'body' => '<p>Hi</p>'], TicketChannel::Portal);
}
