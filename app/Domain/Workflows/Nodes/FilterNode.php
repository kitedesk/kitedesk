<?php

namespace App\Domain\Workflows\Nodes;

use App\Domain\Workflows\Conditions\ConditionEvaluator;
use App\Domain\Workflows\Conditions\ValidConditions;
use App\Domain\Workflows\Engine\NodeResult;
use App\Domain\Workflows\Engine\RunContext;

/**
 * Lets the run through only when the conditions hold. Inside a loop it skips to the next
 * item; elsewhere it ends the run.
 */
class FilterNode extends Node
{
    public function __construct(private ConditionEvaluator $conditions) {}

    public function type(): string
    {
        return 'filter';
    }

    public function rules(): array
    {
        return ['conditions' => ['required', 'array', new ValidConditions]];
    }

    public function execute(array $data, RunContext $context): NodeResult
    {
        if ($this->conditions->passes($data['conditions'] ?? [], $context)) {
            return NodeResult::next(['result' => true]);
        }

        return $context->inLoop()
            ? NodeResult::skipItem(['result' => false])
            : NodeResult::stop(['result' => false]);
    }
}
