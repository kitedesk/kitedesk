<?php

namespace App\Domain\Workflows\Engine;

/**
 * Read access to a workflow graph: nodes by id and where each output leads.
 */
final class Graph
{
    /**
     * @var array<string, array{id: string, type: string, data: array<string, mixed>}>
     */
    private array $nodes = [];

    /**
     * @var array<string, string> "{source}:{handle}" => target
     */
    private array $edges = [];

    /**
     * @param  array{nodes?: list<array<string, mixed>>, edges?: list<array<string, mixed>>}  $graph
     */
    public function __construct(array $graph)
    {
        foreach ($graph['nodes'] ?? [] as $node) {
            $id = (string) ($node['id'] ?? '');
            $this->nodes[$id] = [
                'id' => $id,
                'type' => (string) ($node['type'] ?? ''),
                'data' => is_array($node['data'] ?? null) ? $node['data'] : [],
            ];
        }

        foreach ($graph['edges'] ?? [] as $edge) {
            $this->edges[$edge['source'].':'.($edge['sourceHandle'] ?? 'out')] ??= (string) $edge['target'];
        }
    }

    /**
     * @return array{id: string, type: string, data: array<string, mixed>}|null
     */
    public function node(string $id): ?array
    {
        return $this->nodes[$id] ?? null;
    }

    public function triggerId(): ?string
    {
        foreach ($this->nodes as $id => $node) {
            if ($node['type'] === 'trigger') {
                return $id;
            }
        }

        return null;
    }

    /**
     * The node connected to the given output, or null when the path ends there.
     */
    public function next(string $nodeId, ?string $handle): ?string
    {
        return $handle === null ? null : $this->edges[$nodeId.':'.$handle] ?? null;
    }
}
