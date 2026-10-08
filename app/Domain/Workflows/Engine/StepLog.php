<?php

namespace App\Domain\Workflows\Engine;

use App\Domain\Workflows\Enums\StepStatus;
use App\Domain\Workflows\Models\WorkflowRun;
use RuntimeException;

/**
 * Records what each node did. Real runs write `workflow_run_steps`; simulations keep the steps
 * in memory for the editor.
 */
final class StepLog
{
    public const int MAX_STEPS = 1000;

    /**
     * @var list<array{node_id: string, node_type: string, status: string, handle: string|null, output: array<string, mixed>, iteration: int|null, error: string|null}>
     */
    private array $steps = [];

    private int $count = 0;

    public function __construct(private ?WorkflowRun $run = null) {}

    public function guard(): void
    {
        if (++$this->count > self::MAX_STEPS) {
            throw new RuntimeException(__('The run stopped after :count steps.', ['count' => self::MAX_STEPS]));
        }
    }

    /**
     * @param  array<string, mixed>  $output
     */
    public function record(string $nodeId, string $type, StepStatus $status, ?string $handle, array $output, ?int $iteration, ?string $error = null): void
    {
        $step = [
            'node_id' => $nodeId,
            'node_type' => $type,
            'status' => $status->value,
            'handle' => $handle,
            'output' => $output,
            'iteration' => $iteration,
            'error' => $error,
        ];

        if ($this->run === null) {
            $this->steps[] = $step;

            return;
        }

        $this->run->steps()->create([...$step, 'output' => $output === [] ? null : $output, 'executed_at' => now()]);
    }

    /**
     * @return list<array{node_id: string, node_type: string, status: string, handle: string|null, output: array<string, mixed>, iteration: int|null, error: string|null}>
     */
    public function all(): array
    {
        return $this->steps;
    }
}
