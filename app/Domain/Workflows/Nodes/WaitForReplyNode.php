<?php

namespace App\Domain\Workflows\Nodes;

use App\Domain\Workflows\Engine\NodeResult;
use App\Domain\Workflows\Engine\RunContext;
use App\Domain\Workflows\Nodes\Concerns\WaitsForDuration;

/**
 * Pauses until the customer replies (continues from `replied`) or the time runs out (`timeout`).
 */
class WaitForReplyNode extends Node
{
    use WaitsForDuration;

    public function type(): string
    {
        return 'wait_for_reply';
    }

    public function outputs(array $data): array
    {
        return ['replied', 'timeout'];
    }

    public function rules(): array
    {
        return $this->durationRules();
    }

    public function execute(array $data, RunContext $context): NodeResult
    {
        $until = $this->until($data, $context->ticket);

        return $context->simulating
            ? NodeResult::branch('timeout', ['until' => $until->toIso8601String()])
            : NodeResult::wait($until, 'reply', ['until' => $until->toIso8601String()]);
    }
}
