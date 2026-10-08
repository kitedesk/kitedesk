<?php

namespace App\Domain\Workflows\Engine;

use App\Domain\Tickets\Models\Ticket;
use App\Domain\Workflows\Enums\RunStatus;
use App\Domain\Workflows\Enums\StepStatus;
use App\Domain\Workflows\Jobs\RunWorkflow;
use App\Domain\Workflows\Models\Workflow;
use App\Domain\Workflows\Models\WorkflowRun;
use App\Models\User;
use Illuminate\Http\Client\HttpClientException;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Starts, executes and resumes workflow runs.
 *
 * A run walks the graph from the trigger, following the output each node chooses, until a path
 * ends. "For each" runs its `each` path once per item, then continues from `done`. A wait node
 * parks the run (status `waiting`) until `workflows:resume` or a customer reply picks it up.
 */
class WorkflowEngine
{
    /**
     * Workflows started by other workflows' actions stop being triggered past this depth.
     */
    public const int MAX_DEPTH = 3;

    public function __construct(private NodeRegistry $nodes, private WorkflowOrigin $origin) {}

    /**
     * Create a run for the ticket and queue it.
     *
     * @param  array<string, mixed>  $trigger  The trigger payload (`changes`, `message_id`...).
     */
    public function start(Workflow $workflow, Ticket $ticket, array $trigger = [], int $depth = 0, ?User $startedBy = null): WorkflowRun
    {
        $run = $workflow->runs()->create([
            'ticket_id' => $ticket->id,
            'status' => RunStatus::Running,
            'trigger_event' => $trigger['event'] ?? $workflow->trigger->value,
            'context' => ['trigger' => [...$trigger, 'event' => $trigger['event'] ?? $workflow->trigger->value], 'vars' => []],
            'graph' => $workflow->graph,
            'depth' => $depth,
            'started_by' => $startedBy?->id,
            'started_at' => now(),
        ]);

        $workflow->forceFill(['last_run_at' => now()])->saveQuietly();

        RunWorkflow::dispatch($run);

        return $run;
    }

    /**
     * Wake a waiting run up and continue from the given output of the node it waited on.
     */
    public function resume(WorkflowRun $run, string $handle): bool
    {
        $claimed = WorkflowRun::query()
            ->whereKey($run->id)
            ->where('status', RunStatus::Waiting)
            ->update(['status' => RunStatus::Running, 'resume_at' => null]);

        if ($claimed === 0) {
            return false;
        }

        $run->refresh();
        $run->update(['context' => [...$run->context ?? [], 'resume_handle' => $handle, 'waiting_for' => null]]);

        RunWorkflow::dispatch($run);

        return true;
    }

    /**
     * Execute a run that is in the `running` state.
     */
    public function execute(WorkflowRun $run): void
    {
        $graph = new Graph($run->graph);
        $context = new RunContext(
            ticket: $run->ticket,
            workflowId: $run->workflow_id,
            workflowName: $run->workflow->name,
            trigger: is_array($run->context['trigger'] ?? null) ? $run->context['trigger'] : [],
            vars: is_array($run->context['vars'] ?? null) ? $run->context['vars'] : [],
        );
        $steps = new StepLog($run);

        $start = $run->current_node_id === null
            ? $graph->triggerId()
            : $graph->next($run->current_node_id, (string) ($run->context['resume_handle'] ?? 'out'));

        try {
            $this->origin->actingAs($run->workflow_id, $context->workflowName, $run->depth + 1, fn () => $this->walk($graph, $start, $context, $steps));

            $this->finish($run, $context, RunStatus::Completed);
        } catch (FlowSignal $signal) {
            if ($signal->kind === NodeResult::WAIT) {
                $run->update([
                    'status' => RunStatus::Waiting,
                    'current_node_id' => $signal->nodeId,
                    'resume_at' => $signal->until,
                    'context' => [...$this->contextOf($run, $context), 'waiting_for' => $signal->waitFor, 'resume_handle' => null],
                ]);

                return;
            }

            $this->finish($run, $context, RunStatus::Stopped);
        } catch (Throwable $exception) {
            $this->finish($run, $context, RunStatus::Failed, $exception->getMessage());

            // Expected failures (a blocked URL, an unreachable server) are only shown in the run history.
            if (! $exception instanceof RuntimeException && ! $exception instanceof HttpClientException) {
                report($exception);
            }
        }
    }

    /**
     * Walk the graph against a ticket without changing anything (the editor's "Test" button).
     *
     * @param  array{nodes: list<array<string, mixed>>, edges: list<array<string, mixed>>}  $graph
     * @param  array<string, mixed>  $trigger
     * @return array{status: string, error: string|null, steps: list<array<string, mixed>>}
     */
    public function simulate(array $graph, Ticket $ticket, string $name, array $trigger = []): array
    {
        $walker = new Graph($graph);
        $context = new RunContext($ticket, 0, $name, $trigger, simulating: true);
        $steps = new StepLog;
        $status = RunStatus::Completed;
        $error = null;

        try {
            $this->walk($walker, $walker->triggerId(), $context, $steps);
        } catch (FlowSignal) {
            $status = RunStatus::Stopped;
        } catch (Throwable $exception) {
            $status = RunStatus::Failed;
            $error = $exception->getMessage();
        }

        return ['status' => $status->value, 'error' => $error, 'steps' => $steps->all()];
    }

    private function walk(Graph $graph, ?string $nodeId, RunContext $context, StepLog $steps): void
    {
        while ($nodeId !== null) {
            $node = $graph->node($nodeId) ?? throw new RuntimeException(__('The workflow refers to a step that no longer exists.'));
            $handler = $this->nodes->get($node['type']) ?? throw new RuntimeException(__('Unknown step type ":type".', ['type' => $node['type']]));

            $steps->guard();

            try {
                $result = $handler->execute($node['data'], $context);
            } catch (Throwable $exception) {
                $steps->record($nodeId, $node['type'], StepStatus::Failed, null, [], $context->iteration(), Str::limit($exception->getMessage(), 1000));

                throw $exception;
            }

            if ($result->control === NodeResult::WAIT && $context->inLoop()) {
                throw new RuntimeException(__('Waiting is not possible inside a loop.'));
            }

            $steps->record(
                $nodeId,
                $node['type'],
                match ($result->control) {
                    NodeResult::WAIT => StepStatus::Waiting,
                    NodeResult::CONTINUE_LOOP, NodeResult::STOP, NodeResult::BREAK_LOOP => StepStatus::Skipped,
                    default => StepStatus::Succeeded,
                },
                $result->items !== null ? 'each' : $result->handle,
                $result->output,
                $context->iteration(),
            );

            if ($result->control !== null) {
                throw FlowSignal::from($result, $nodeId);
            }

            if ($result->items !== null) {
                $this->loop($graph, $nodeId, $result->items, $context, $steps);
            }

            $nodeId = $graph->next($nodeId, $result->handle);
        }
    }

    /**
     * @param  list<mixed>  $items
     */
    private function loop(Graph $graph, string $nodeId, array $items, RunContext $context, StepLog $steps): void
    {
        $body = $graph->next($nodeId, 'each');

        if ($body === null || $items === []) {
            return;
        }

        $context->enterLoop($nodeId, $items);

        try {
            foreach (array_keys($items) as $index) {
                $context->setLoopIndex($index);

                try {
                    $this->walk($graph, $body, $context, $steps);
                } catch (FlowSignal $signal) {
                    if ($signal->kind === NodeResult::CONTINUE_LOOP) {
                        continue;
                    }

                    if ($signal->kind === NodeResult::BREAK_LOOP) {
                        break;
                    }

                    throw $signal;
                }
            }
        } finally {
            $context->leaveLoop();
        }
    }

    private function finish(WorkflowRun $run, RunContext $context, RunStatus $status, ?string $error = null): void
    {
        $run->update([
            'status' => $status,
            'context' => $this->contextOf($run, $context),
            'current_node_id' => null,
            'resume_at' => null,
            'error' => $error !== null ? Str::limit($error, 1000) : null,
            'finished_at' => now(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function contextOf(WorkflowRun $run, RunContext $context): array
    {
        return [...$run->context ?? [], 'vars' => $context->vars];
    }
}
