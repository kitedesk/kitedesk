<?php

namespace App\Domain\Workflows\Nodes;

use App\Domain\Workflows\Conditions\ConditionEvaluator;
use App\Domain\Workflows\Conditions\ValidConditions;
use App\Domain\Workflows\Engine\NodeResult;
use App\Domain\Workflows\Engine\RunContext;

/**
 * Continues from `true` or `false` depending on the conditions.
 */
class IfNode extends Node
{
    public function __construct(private ConditionEvaluator $conditions) {}

    public function type(): string
    {
        return 'if';
    }

    public function outputs(array $data): array
    {
        return ['true', 'false'];
    }

    public function rules(): array
    {
        return ['conditions' => ['required', 'array', new ValidConditions]];
    }

    public function execute(array $data, RunContext $context): NodeResult
    {
        $passes = $this->conditions->passes($data['conditions'] ?? [], $context);

        return NodeResult::branch($passes ? 'true' : 'false', ['result' => $passes]);
    }
}
