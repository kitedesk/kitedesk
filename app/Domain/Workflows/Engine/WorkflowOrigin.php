<?php

namespace App\Domain\Workflows\Engine;

/**
 * Remembers which workflow run is acting right now, so the ticket events its actions cause
 * carry that origin and the engine can stop workflows from triggering each other forever.
 *
 * Bound as a scoped singleton: queue workers start each job with a clean slate.
 */
class WorkflowOrigin
{
    /**
     * @var list<array{workflow_id: int|null, workflow_name: string|null, depth: int}>
     */
    private array $stack = [];

    public function workflowId(): ?int
    {
        return $this->current()['workflow_id'];
    }

    public function workflowName(): ?string
    {
        return $this->current()['workflow_name'];
    }

    /**
     * How many workflow runs led to what is happening now (0 = a person or the system).
     */
    public function depth(): int
    {
        return $this->current()['depth'];
    }

    /**
     * Run the callback as the given workflow run.
     *
     * @template TResult
     *
     * @param  callable(): TResult  $callback
     * @return TResult
     */
    public function actingAs(int $workflowId, string $workflowName, int $depth, callable $callback): mixed
    {
        $this->stack[] = ['workflow_id' => $workflowId, 'workflow_name' => $workflowName, 'depth' => $depth];

        try {
            return $callback();
        } finally {
            array_pop($this->stack);
        }
    }

    /**
     * @return array{workflow_id: int|null, workflow_name: string|null, depth: int}
     */
    private function current(): array
    {
        return $this->stack === [] ? ['workflow_id' => null, 'workflow_name' => null, 'depth' => 0] : $this->stack[array_key_last($this->stack)];
    }
}
