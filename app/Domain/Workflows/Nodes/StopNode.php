<?php

namespace App\Domain\Workflows\Nodes;

use App\Domain\Workflows\Engine\NodeResult;
use App\Domain\Workflows\Engine\RunContext;
use Illuminate\Validation\Rule;

/**
 * Ends the run, or with `scope: loop` leaves the current loop and continues after it.
 */
class StopNode extends Node
{
    public function type(): string
    {
        return 'stop';
    }

    public function outputs(array $data): array
    {
        return [];
    }

    public function rules(): array
    {
        return ['scope' => ['nullable', Rule::in(['run', 'loop'])]];
    }

    public function execute(array $data, RunContext $context): NodeResult
    {
        return ($data['scope'] ?? 'run') === 'loop' && $context->inLoop()
            ? NodeResult::breakLoop()
            : NodeResult::stop();
    }
}
