<?php

namespace Database\Factories;

use App\Domain\Workflows\Enums\WorkflowTrigger;
use App\Domain\Workflows\Models\Workflow;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Workflow>
 */
class WorkflowFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => rtrim(fake()->sentence(3), '.'),
            'is_active' => true,
            'trigger' => WorkflowTrigger::TicketCreated,
            'graph' => self::graph(WorkflowTrigger::TicketCreated, [], []),
            'max_runs_per_ticket' => 1,
            'apply_to_existing' => false,
        ];
    }

    /**
     * A workflow whose steps run one after another (each `out` leads to the next step).
     *
     * @param  list<array{0: string, 1?: array<string, mixed>}>  $steps  [type, settings] pairs.
     * @param  array<string, mixed>  $settings  Trigger settings.
     */
    public function chain(WorkflowTrigger $trigger, array $steps, array $settings = []): static
    {
        $nodes = [];
        $edges = [];
        $previous = 'trigger';

        foreach ($steps as $index => $step) {
            $id = 's'.($index + 1);
            $nodes[$id] = [$step[0], $step[1] ?? []];
            $edges[] = [$previous, 'out', $id];
            $previous = $id;
        }

        return $this->flow($trigger, $nodes, $edges, $settings);
    }

    /**
     * A workflow with any shape. The trigger node's id is `trigger`.
     *
     * @param  array<string, array{0: string, 1?: array<string, mixed>}>  $nodes  id => [type, settings]
     * @param  list<array{0: string, 1: string, 2: string}>  $edges  [source, handle, target]
     * @param  array<string, mixed>  $settings  Trigger settings.
     */
    public function flow(WorkflowTrigger $trigger, array $nodes, array $edges, array $settings = []): static
    {
        return $this->state([
            'trigger' => $trigger,
            'graph' => self::graph($trigger, $nodes, $edges, $settings),
        ]);
    }

    /**
     * @param  array<string, array{0: string, 1?: array<string, mixed>}>  $nodes
     * @param  list<array{0: string, 1: string, 2: string}>  $edges
     * @param  array<string, mixed>  $settings
     * @return array{nodes: list<array<string, mixed>>, edges: list<array<string, mixed>>}
     */
    public static function graph(WorkflowTrigger $trigger, array $nodes, array $edges, array $settings = []): array
    {
        $graphNodes = [['id' => 'trigger', 'type' => 'trigger', 'position' => ['x' => 0, 'y' => 0], 'data' => ['event' => $trigger->value, ...$settings]]];
        $y = 0;

        foreach ($nodes as $id => $node) {
            $graphNodes[] = ['id' => (string) $id, 'type' => $node[0], 'position' => ['x' => 0, 'y' => $y += 120], 'data' => $node[1] ?? []];
        }

        return [
            'nodes' => $graphNodes,
            'edges' => array_map(fn (array $edge): array => [
                'id' => "{$edge[0]}-{$edge[1]}-{$edge[2]}",
                'source' => $edge[0],
                'sourceHandle' => $edge[1],
                'target' => $edge[2],
            ], $edges),
        ];
    }
}
