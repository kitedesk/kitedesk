<?php

namespace App\Domain\Workflows\Nodes;

use App\Domain\Workflows\Engine\NodeResult;
use App\Domain\Workflows\Engine\RunContext;
use App\Domain\Workflows\Nodes\Concerns\WaitsForDuration;

/**
 * Pauses the run; `workflows:resume` picks it up again when the time is up.
 */
class WaitNode extends Node
{
    use WaitsForDuration;

    public function type(): string
    {
        return 'wait';
    }

    public function rules(): array
    {
        return $this->durationRules();
    }

    public function execute(array $data, RunContext $context): NodeResult
    {
        $until = $this->until($data, $context->ticket);

        return $context->simulating
            ? NodeResult::next(['until' => $until->toIso8601String()])
            : NodeResult::wait($until, output: ['until' => $until->toIso8601String()]);
    }
}
