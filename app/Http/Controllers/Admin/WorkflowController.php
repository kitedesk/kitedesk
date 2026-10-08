<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Accounts\Models\Group;
use App\Domain\Accounts\Models\Organization;
use App\Domain\Ai\Support\AiSettings;
use App\Domain\Entitlements\Enums\Limit;
use App\Domain\Entitlements\Support\PlanLimits;
use App\Domain\Support\EnumOptions;
use App\Domain\Tickets\Enums\TicketChannel;
use App\Domain\Tickets\Enums\TicketPriority;
use App\Domain\Tickets\Enums\TicketStatus;
use App\Domain\Tickets\Enums\TicketType;
use App\Domain\Tickets\Models\TicketField;
use App\Domain\Tickets\Support\CustomStatuses;
use App\Domain\Tickets\Support\TicketCatalog;
use App\Domain\Workflows\Enums\ConditionOperator;
use App\Domain\Workflows\Enums\IdleAnchor;
use App\Domain\Workflows\Enums\RunStatus;
use App\Domain\Workflows\Enums\WorkflowTrigger;
use App\Domain\Workflows\Models\Workflow;
use App\Domain\Workflows\Models\WorkflowRun;
use App\Domain\Workflows\Models\WorkflowRunStep;
use App\Domain\Workflows\Support\WorkflowTemplates;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveWorkflowRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WorkflowController extends Controller
{
    public function index(): Response
    {
        $since = now()->subDays(7);

        return Inertia::render('admin/workflows/index', [
            'workflows' => Workflow::query()
                ->withCount([
                    'runs as runs_last_week' => fn (Builder $runs) => $runs->where('created_at', '>=', $since),
                    'runs as failures_last_week' => fn (Builder $runs) => $runs->where('created_at', '>=', $since)->where('status', RunStatus::Failed),
                ])
                ->orderBy('name')
                ->get()
                ->map(fn (Workflow $workflow): array => [
                    'id' => $workflow->id,
                    'name' => $workflow->name,
                    'description' => $workflow->description,
                    'is_active' => $workflow->is_active,
                    'trigger' => $workflow->trigger->value,
                    'trigger_label' => $workflow->trigger->label(),
                    'disabled_reason' => $workflow->disabled_reason,
                    'steps' => count($workflow->graph['nodes']) - 1,
                    'runs_last_week' => $workflow->getAttribute('runs_last_week'),
                    'failures_last_week' => $workflow->getAttribute('failures_last_week'),
                    'last_run_at' => $workflow->last_run_at?->toIso8601String(),
                ]),
            'templates' => WorkflowTemplates::all(),
        ]);
    }

    public function create(Request $request): Response
    {
        $template = WorkflowTemplates::get($request->string('template')->toString());

        return Inertia::render('admin/workflows/editor', [
            'workflow' => [
                'id' => null,
                'name' => $template['name'],
                'description' => $template['description'],
                'is_active' => false,
                'max_runs_per_ticket' => 1,
                'apply_to_existing' => false,
                'disabled_reason' => null,
                'graph' => $template['graph'],
            ],
            'options' => $this->options(),
            'runs' => [],
            'selectedRun' => null,
        ]);
    }

    public function store(SaveWorkflowRequest $request): RedirectResponse
    {
        $workflow = Workflow::query()->create([
            ...$request->workflowAttributes(),
            'created_by' => $request->user()?->id,
            'updated_by' => $request->user()?->id,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Workflow created.')]);

        return to_route('admin.workflows.edit', $workflow);
    }

    public function edit(Request $request, Workflow $workflow): Response
    {
        return Inertia::render('admin/workflows/editor', [
            'workflow' => $this->serialize($workflow),
            'options' => $this->options(),
            'runs' => fn (): array => $this->runs($workflow),
            'selectedRun' => fn (): ?array => $this->selectedRun($workflow, $request->integer('run')),
        ]);
    }

    public function update(SaveWorkflowRequest $request, Workflow $workflow): RedirectResponse
    {
        $workflow->update([...$request->workflowAttributes(), 'updated_by' => $request->user()?->id]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Workflow saved.')]);

        return back();
    }

    public function destroy(Workflow $workflow): RedirectResponse
    {
        $workflow->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Workflow deleted.')]);

        return to_route('admin.workflows.index');
    }

    public function toggle(Workflow $workflow): RedirectResponse
    {
        $planError = $workflow->is_active ? null : PlanLimits::errorFor(Limit::ActiveWorkflows);

        if ($planError !== null) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $planError]);

            return back();
        }

        $workflow->update(['is_active' => ! $workflow->is_active]);

        return back();
    }

    public function duplicate(Request $request, Workflow $workflow): RedirectResponse
    {
        $copy = $workflow->replicate(['last_run_at', 'activated_at', 'disabled_reason']);
        $copy->fill([
            'name' => __(':name (copy)', ['name' => $workflow->name]),
            'is_active' => false,
            'created_by' => $request->user()?->id,
            'updated_by' => $request->user()?->id,
        ])->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Workflow duplicated.')]);

        return to_route('admin.workflows.edit', $copy);
    }

    /**
     * Stop a run that is waiting or still going.
     */
    public function cancelRun(Workflow $workflow, WorkflowRun $run): RedirectResponse
    {
        abort_unless($run->workflow_id === $workflow->id, 404);

        WorkflowRun::query()
            ->whereKey($run->id)
            ->whereIn('status', [RunStatus::Waiting, RunStatus::Running])
            ->update(['status' => RunStatus::Cancelled, 'resume_at' => null, 'finished_at' => now()]);

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(Workflow $workflow): array
    {
        return [
            'id' => $workflow->id,
            'name' => $workflow->name,
            'description' => $workflow->description,
            'is_active' => $workflow->is_active,
            'max_runs_per_ticket' => $workflow->max_runs_per_ticket,
            'apply_to_existing' => $workflow->apply_to_existing,
            'disabled_reason' => $workflow->disabled_reason,
            'graph' => $workflow->graph,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function runs(Workflow $workflow): array
    {
        return array_values($workflow->runs()
            ->with('ticket:id,number,subject')
            ->latest('id')
            ->limit(30)
            ->get()
            ->map(fn (WorkflowRun $run): array => [
                'id' => $run->id,
                'status' => $run->status->value,
                'status_label' => $run->status->label(),
                'trigger_event' => $run->trigger_event,
                'ticket' => ['id' => $run->ticket->id, 'number' => $run->ticket->reference(), 'subject' => $run->ticket->subject],
                'error' => $run->error,
                'resume_at' => $run->resume_at?->toIso8601String(),
                'started_at' => $run->started_at?->toIso8601String(),
                'finished_at' => $run->finished_at?->toIso8601String(),
            ])
            ->all());
    }

    /**
     * @return array<string, mixed>|null
     */
    private function selectedRun(Workflow $workflow, int $runId): ?array
    {
        $run = $runId > 0 ? $workflow->runs()->find($runId) : null;

        if ($run === null) {
            return null;
        }

        return [
            'id' => $run->id,
            'graph' => $run->graph,
            'vars' => $run->context['vars'] ?? [],
            'steps' => $run->steps()->get()->map(fn (WorkflowRunStep $step): array => [
                'node_id' => $step->node_id,
                'node_type' => $step->node_type,
                'status' => $step->status->value,
                'handle' => $step->handle,
                'output' => $step->output ?? [],
                'iteration' => $step->iteration,
                'error' => $step->error,
                'executed_at' => $step->executed_at->toIso8601String(),
            ])->values()->all(),
        ];
    }

    /**
     * Everything the editor's forms offer.
     *
     * @return array<string, mixed>
     */
    private function options(): array
    {
        return [
            'triggers' => EnumOptions::for(WorkflowTrigger::class),
            'operators' => EnumOptions::for(ConditionOperator::class),
            'anchors' => EnumOptions::for(IdleAnchor::class),
            'statuses' => EnumOptions::for(TicketStatus::class),
            'customStatuses' => CustomStatuses::options(),
            'priorities' => EnumOptions::for(TicketPriority::class),
            'types' => EnumOptions::for(TicketType::class),
            'channels' => EnumOptions::for(TicketChannel::class),
            'categories' => TicketCatalog::categories(),
            'groups' => Group::query()->orderBy('name')->get(['id', 'name']),
            'organizations' => Organization::query()->orderBy('name')->get(['id', 'name']),
            'agents' => User::query()->staff()->orderBy('name')->get(['id', 'name']),
            'fields' => TicketField::query()->ordered()->get()->map(fn (TicketField $field): array => $field->toFormArray(required: false)),
            'aiAvailable' => AiSettings::current()->isAvailable(),
        ];
    }
}
