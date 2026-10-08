<?php

namespace App\Console\Commands;

use App\Domain\Entitlements\Enums\Feature;
use App\Domain\Entitlements\Support\PlanLimits;
use App\Domain\Workflows\Engine\WorkflowEngine;
use App\Domain\Workflows\Enums\RunStatus;
use App\Domain\Workflows\Models\WorkflowRun;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('workflows:resume')]
#[Description('Continue workflow runs whose wait is over')]
class ResumeWorkflowRuns extends Command
{
    public function handle(WorkflowEngine $engine): int
    {
        if (! PlanLimits::allows(Feature::Workflows)) {
            return self::SUCCESS;
        }

        $resumed = 0;

        WorkflowRun::query()
            ->where('status', RunStatus::Waiting)
            ->where('resume_at', '<=', now())
            ->orderBy('resume_at')
            ->limit(500)
            ->get()
            ->each(function (WorkflowRun $run) use ($engine, &$resumed): void {
                // A "wait for reply" that ran out of time continues from its timeout output.
                $handle = ($run->context['waiting_for'] ?? null) === 'reply' ? 'timeout' : 'out';

                if ($engine->resume($run, $handle)) {
                    $resumed++;
                }
            });

        $this->components->info("Resumed {$resumed} workflow run(s).");

        return self::SUCCESS;
    }
}
