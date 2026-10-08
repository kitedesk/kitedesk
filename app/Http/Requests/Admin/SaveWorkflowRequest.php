<?php

namespace App\Http\Requests\Admin;

use App\Domain\Entitlements\Enums\Limit;
use App\Domain\Entitlements\Support\PlanLimits;
use App\Domain\Workflows\Engine\GraphValidator;
use App\Domain\Workflows\Enums\WorkflowTrigger;
use App\Domain\Workflows\Models\Workflow;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class SaveWorkflowRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['boolean'],
            'max_runs_per_ticket' => ['required', 'integer', 'min:0', 'max:'.Workflow::HARD_RUN_LIMIT],
            'apply_to_existing' => ['boolean'],
            'graph' => ['required', 'array'],
            'graph.nodes' => ['present', 'array'],
            'graph.edges' => ['present', 'array'],
        ];
    }

    /**
     * Check the graph's shape, connections and every node's settings.
     *
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                foreach (app(GraphValidator::class)->validate($this->input('graph')) as $key => $message) {
                    $validator->errors()->add($key, $message);
                }

                $workflow = $this->route('workflow');
                $activating = $this->boolean('is_active') && ! ($workflow instanceof Workflow && $workflow->is_active);
                $planError = $activating ? PlanLimits::errorFor(Limit::ActiveWorkflows) : null;

                if ($planError !== null) {
                    $validator->errors()->add('is_active', $planError);
                }
            },
        ];
    }

    /**
     * @return array{name: string, description: string|null, is_active: bool, max_runs_per_ticket: int, apply_to_existing: bool, trigger: WorkflowTrigger, graph: array{nodes: list<array<string, mixed>>, edges: list<array<string, mixed>>}}
     */
    public function workflowAttributes(): array
    {
        $graph = self::cleanGraph((array) $this->input('graph'));

        return [
            'name' => $this->string('name')->trim()->toString(),
            'description' => $this->filled('description') ? $this->string('description')->trim()->toString() : null,
            'is_active' => $this->boolean('is_active'),
            'max_runs_per_ticket' => $this->integer('max_runs_per_ticket'),
            'apply_to_existing' => $this->boolean('apply_to_existing'),
            'trigger' => GraphValidator::triggerOf($graph) ?? WorkflowTrigger::Manual,
            'graph' => $graph,
        ];
    }

    /**
     * Keep only what the engine and the editor use (React Flow adds UI state such as
     * `selected`, `measured` and `dragging`).
     *
     * @param  array<mixed>  $graph
     * @return array{nodes: list<array<string, mixed>>, edges: list<array<string, mixed>>}
     */
    public static function cleanGraph(array $graph): array
    {
        $nodes = array_map(fn (array $node): array => [
            'id' => (string) $node['id'],
            'type' => (string) $node['type'],
            'position' => [
                'x' => round((float) ($node['position']['x'] ?? 0), 1),
                'y' => round((float) ($node['position']['y'] ?? 0), 1),
            ],
            'data' => is_array($node['data'] ?? null) ? $node['data'] : [],
        ], array_values(array_filter((array) ($graph['nodes'] ?? []), is_array(...))));

        $edges = array_map(fn (array $edge): array => [
            'id' => (string) ($edge['id'] ?? "{$edge['source']}-{$edge['target']}"),
            'source' => (string) $edge['source'],
            'sourceHandle' => (string) ($edge['sourceHandle'] ?? 'out'),
            'target' => (string) $edge['target'],
        ], array_values(array_filter((array) ($graph['edges'] ?? []), is_array(...))));

        return ['nodes' => $nodes, 'edges' => $edges];
    }
}
