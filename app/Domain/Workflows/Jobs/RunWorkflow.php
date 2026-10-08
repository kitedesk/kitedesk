<?php

namespace App\Domain\Workflows\Jobs;

use App\Domain\Workflows\Engine\WorkflowEngine;
use App\Domain\Workflows\Enums\RunStatus;
use App\Domain\Workflows\Models\WorkflowRun;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Executes a workflow run until it ends or waits.
 */
class RunWorkflow implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    /**
     * AI steps can each take up to a minute.
     */
    public int $timeout = 300;

    public int $uniqueFor = 300;

    public function __construct(public WorkflowRun $run) {}

    public function uniqueId(): string
    {
        return (string) $this->run->id;
    }

    public function handle(WorkflowEngine $engine): void
    {
        $this->run->refresh();

        if ($this->run->status !== RunStatus::Running) {
            return;
        }

        $engine->execute($this->run);
    }

    public function failed(?Throwable $exception): void
    {
        $this->run->update([
            'status' => RunStatus::Failed,
            'error' => $exception?->getMessage() ?? __('The run did not finish.'),
            'finished_at' => now(),
        ]);
    }
}
