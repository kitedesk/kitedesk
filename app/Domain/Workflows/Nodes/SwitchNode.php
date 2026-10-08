<?php

namespace App\Domain\Workflows\Nodes;

use App\Domain\Workflows\Conditions\ConditionEvaluator;
use App\Domain\Workflows\Conditions\FieldResolver;
use App\Domain\Workflows\Engine\NodeResult;
use App\Domain\Workflows\Engine\Placeholders;
use App\Domain\Workflows\Engine\RunContext;
use App\Domain\Workflows\Enums\ConditionOperator;
use Closure;

/**
 * Continues from the first case whose value matches the field, else from `default`.
 *
 * Settings: `field`, and `cases`: list<{id, value}> (each case id is an output).
 */
class SwitchNode extends Node
{
    public function __construct(private FieldResolver $fields, private ConditionEvaluator $conditions, private Placeholders $placeholders) {}

    public function type(): string
    {
        return 'switch';
    }

    public function outputs(array $data): array
    {
        $cases = array_map(fn (array $case): string => (string) $case['id'], is_array($data['cases'] ?? null) ? $data['cases'] : []);

        return array_values([...$cases, 'default']);
    }

    public function rules(): array
    {
        return [
            'field' => ['required', 'string', function (string $attribute, mixed $value, Closure $fail): void {
                if (! is_string($value) || ! FieldResolver::isKnown($value)) {
                    $fail(__('Choose a field to compare.'));
                }
            }],
            'cases' => ['required', 'array', 'min:1', 'max:20'],
            'cases.*.id' => ['required', 'string', 'max:40', 'distinct', 'not_in:default'],
            'cases.*.value' => ['present', 'nullable', 'string', 'max:255'],
        ];
    }

    public function execute(array $data, RunContext $context): NodeResult
    {
        $actual = $this->fields->value((string) $data['field'], $context);

        foreach ($data['cases'] ?? [] as $case) {
            $expected = $this->placeholders->text((string) ($case['value'] ?? ''), $context);

            if ($this->conditions->compare($actual, ConditionOperator::Is, $expected)) {
                return NodeResult::branch((string) $case['id'], ['matched' => $expected]);
            }
        }

        return NodeResult::branch('default', ['matched' => null]);
    }
}
