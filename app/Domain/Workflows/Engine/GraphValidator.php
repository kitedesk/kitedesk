<?php

namespace App\Domain\Workflows\Engine;

use App\Domain\Workflows\Enums\WorkflowTrigger;
use Illuminate\Support\Facades\Validator;

/**
 * Checks a graph before it is saved or tested:
 *
 * - exactly one trigger, known node types, each node's settings valid
 * - edges between existing nodes, from outputs the node has, one edge per output
 * - no cycles, and every node reachable from the trigger
 * - loops: a node is either inside a loop body or after it, at most 3 nested loops,
 *   no waiting inside a loop, and `{{item.*}}` only inside a loop
 * - `{{vars.*}}` only for variables some node sets
 *
 * Errors are keyed `graph` (the whole graph) or `nodes.{id}` (one node).
 */
class GraphValidator
{
    public const int MAX_NODES = 50;

    public const int MAX_LOOP_DEPTH = 3;

    /**
     * @var array<string, string>
     */
    private array $errors = [];

    public function __construct(private NodeRegistry $registry) {}

    /**
     * @return array<string, string>
     */
    public function validate(mixed $graph): array
    {
        $this->errors = [];

        if (! is_array($graph) || ! is_array($graph['nodes'] ?? null) || ! is_array($graph['edges'] ?? null)) {
            return ['graph' => __('The workflow is invalid.')];
        }

        $nodes = $this->nodes($graph['nodes']);

        if ($nodes === null) {
            return $this->errors;
        }

        $triggers = array_keys(array_filter($nodes, fn (array $node): bool => $node['type'] === 'trigger'));

        if (count($triggers) !== 1) {
            return ['graph' => __('A workflow needs exactly one trigger.')];
        }

        foreach ($nodes as $id => $node) {
            $this->validateSettings($id, $node);
        }

        $edges = $this->edges($graph['edges'], $nodes);

        if ($edges === null) {
            return $this->errors;
        }

        if ($this->hasCycle($nodes, $edges)) {
            return [...$this->errors, 'graph' => __('Steps cannot loop back to an earlier step. Use "For each" to repeat steps.')];
        }

        $reachable = $this->reachableFrom($triggers[0], $edges);

        foreach (array_keys($nodes) as $id) {
            if (! isset($reachable[$id])) {
                $this->fail($id, __('Connect this step to the workflow, or delete it.'));
            }
        }

        $this->validateLoops($nodes, $edges);
        $this->validateVariables($nodes);

        return $this->errors;
    }

    /**
     * The trigger event of a valid graph.
     *
     * @param  array{nodes: list<array<string, mixed>>}  $graph
     */
    public static function triggerOf(array $graph): ?WorkflowTrigger
    {
        foreach ($graph['nodes'] as $node) {
            if (($node['type'] ?? null) === 'trigger') {
                return WorkflowTrigger::tryFrom((string) ($node['data']['event'] ?? ''));
            }
        }

        return null;
    }

    /**
     * @param  array<mixed>  $rawNodes
     * @return array<string, array{type: string, data: array<string, mixed>}>|null
     */
    private function nodes(array $rawNodes): ?array
    {
        if (count($rawNodes) > self::MAX_NODES) {
            $this->errors['graph'] = __('A workflow can have at most :count steps.', ['count' => self::MAX_NODES]);

            return null;
        }

        $nodes = [];

        foreach ($rawNodes as $node) {
            $id = is_array($node) ? ($node['id'] ?? null) : null;
            $type = is_array($node) ? ($node['type'] ?? null) : null;

            if (! is_string($id) || $id === '' || strlen($id) > 64 || isset($nodes[$id]) || ! is_string($type)) {
                $this->errors['graph'] = __('The workflow is invalid.');

                return null;
            }

            if (! $this->registry->has($type)) {
                $this->errors['graph'] = __('Unknown step type ":type".', ['type' => $type]);

                return null;
            }

            $nodes[$id] = ['type' => $type, 'data' => is_array($node['data'] ?? null) ? $node['data'] : []];
        }

        return $nodes;
    }

    /**
     * @param  array{type: string, data: array<string, mixed>}  $node
     */
    private function validateSettings(string $id, array $node): void
    {
        $handler = $this->registry->get($node['type']);

        if ($handler === null) {
            return;
        }

        $validator = Validator::make($node['data'], $handler->rules());

        if ($validator->fails()) {
            $this->fail($id, (string) $validator->errors()->first());
        }
    }

    /**
     * @param  array<mixed>  $rawEdges
     * @param  array<string, array{type: string, data: array<string, mixed>}>  $nodes
     * @return array<string, array<string, string>>|null source => [handle => target]
     */
    private function edges(array $rawEdges, array $nodes): ?array
    {
        $edges = [];

        foreach ($rawEdges as $edge) {
            $source = is_array($edge) ? ($edge['source'] ?? null) : null;
            $target = is_array($edge) ? ($edge['target'] ?? null) : null;
            $handle = is_array($edge) ? ($edge['sourceHandle'] ?? null) ?? 'out' : 'out';

            if (! is_string($source) || ! is_string($target) || ! is_string($handle) || ! isset($nodes[$source], $nodes[$target])) {
                $this->errors['graph'] = __('The workflow is invalid.');

                return null;
            }

            $outputs = $this->registry->get($nodes[$source]['type'])?->outputs($nodes[$source]['data']) ?? [];

            if (! in_array($handle, $outputs, true)) {
                $this->fail($source, __('This step has a connection from an output it doesn\'t have. Reconnect it.'));

                continue;
            }

            if ($nodes[$target]['type'] === 'trigger' || $source === $target) {
                $this->errors['graph'] = __('Steps cannot loop back to an earlier step. Use "For each" to repeat steps.');

                return null;
            }

            if (isset($edges[$source][$handle])) {
                $this->fail($source, __('Each output can connect to only one step.'));

                continue;
            }

            $edges[$source][$handle] = $target;
        }

        return $edges;
    }

    /**
     * @param  array<string, mixed>  $nodes
     * @param  array<string, array<string, string>>  $edges
     */
    private function hasCycle(array $nodes, array $edges): bool
    {
        $state = [];

        $visit = function (string $id) use (&$visit, &$state, $edges): bool {
            $state[$id] = 'visiting';

            foreach ($edges[$id] ?? [] as $target) {
                if (($state[$target] ?? null) === 'visiting' || (! isset($state[$target]) && $visit($target))) {
                    return true;
                }
            }

            $state[$id] = 'done';

            return false;
        };

        foreach (array_keys($nodes) as $id) {
            if (! isset($state[$id]) && $visit($id)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, array<string, string>>  $edges
     * @return array<string, true>
     */
    private function reachableFrom(?string $start, array $edges): array
    {
        $seen = [];
        $queue = $start !== null ? [$start] : [];

        while ($queue !== []) {
            $id = array_shift($queue);

            if (isset($seen[$id])) {
                continue;
            }

            $seen[$id] = true;
            array_push($queue, ...array_values($edges[$id] ?? []));
        }

        return $seen;
    }

    /**
     * @param  array<string, array{type: string, data: array<string, mixed>}>  $nodes
     * @param  array<string, array<string, string>>  $edges
     */
    private function validateLoops(array $nodes, array $edges): void
    {
        /** @var array<string, int> $loopDepth */
        $loopDepth = array_fill_keys(array_keys($nodes), 0);

        foreach ($nodes as $id => $node) {
            if ($node['type'] !== 'for_each') {
                continue;
            }

            $body = $this->reachableFrom($edges[$id]['each'] ?? null, $edges);
            $after = $this->reachableFrom($edges[$id]['done'] ?? null, $edges);

            if (array_intersect_key($body, $after) !== []) {
                $this->fail($id, __('Steps inside the loop cannot also continue after it. End the loop path, or use "done" for what follows.'));
            }

            foreach (array_keys($body) as $member) {
                $loopDepth[$member]++;

                if (in_array($nodes[$member]['type'], NodeRegistry::WAITING_TYPES, true)) {
                    $this->fail($member, __('Waiting is not possible inside a loop.'));
                }
            }
        }

        foreach ($nodes as $id => $node) {
            $depth = $loopDepth[$id] + ($node['type'] === 'for_each' ? 1 : 0);

            if ($depth > self::MAX_LOOP_DEPTH) {
                $this->fail($id, __('Loops can be nested at most :count levels deep.', ['count' => self::MAX_LOOP_DEPTH]));
            }

            if ($loopDepth[$id] === 0 && $this->usesItem($node)) {
                $this->fail($id, __('Loop item values can only be used inside a "For each" loop.'));
            }

            if ($loopDepth[$id] === 0 && ($node['data']['apply_to'] ?? null) === 'item') {
                $this->fail($id, __('Only steps inside a "For each" loop can act on the loop item.'));
            }
        }
    }

    /**
     * @param  array<string, array{type: string, data: array<string, mixed>}>  $nodes
     */
    private function validateVariables(array $nodes): void
    {
        $defined = [];

        foreach ($nodes as $node) {
            $name = match ($node['type']) {
                'set_variable' => $node['data']['name'] ?? null,
                'http_request' => $node['data']['save_as'] ?? null,
                default => null,
            };

            if (is_string($name) && $name !== '') {
                $defined[mb_strtolower($name)] = true;
            }
        }

        foreach ($nodes as $id => $node) {
            $json = (string) json_encode($node['data']);
            preg_match_all('/(?:\{\{\s*|"field":"|"variable":")vars\.([a-z][a-z0-9_]*)/i', $json, $matches);

            if ($node['type'] === 'for_each' && ($node['data']['collection'] ?? null) === 'variable') {
                $matches[1][] = explode('.', (string) ($node['data']['variable'] ?? ''))[0];
            }

            foreach (array_unique($matches[1]) as $name) {
                if (! isset($defined[mb_strtolower($name)])) {
                    $this->fail($id, __('The variable ":name" is not set by any step.', ['name' => $name]));
                }
            }
        }
    }

    /**
     * @param  array{type: string, data: array<string, mixed>}  $node
     */
    private function usesItem(array $node): bool
    {
        $json = (string) json_encode($node['data']);

        return preg_match('/\{\{\s*(item|loop)[.}\s]|"field":"item[."]/i', $json) === 1;
    }

    private function fail(string $nodeId, string $message): void
    {
        $this->errors["nodes.{$nodeId}"] ??= $message;
    }
}
