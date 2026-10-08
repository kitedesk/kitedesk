<?php

namespace App\Domain\Workflows\Conditions;

use App\Domain\Workflows\Enums\ConditionOperator;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Validates a condition group: known fields and operators, valid patterns, at most 3 levels
 * of nested groups and 30 conditions.
 */
class ValidConditions implements ValidationRule
{
    private const int MAX_DEPTH = 3;

    private const int MAX_CONDITIONS = 30;

    private int $count = 0;

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $this->count = 0;

        if (! is_array($value)) {
            $fail(__('The conditions are invalid.'));

            return;
        }

        $error = $this->check($value, 1);

        if ($error !== null) {
            $fail($error);
        }
    }

    /**
     * @param  array<mixed>  $group
     */
    private function check(array $group, int $depth): ?string
    {
        if ($depth > self::MAX_DEPTH) {
            return __('Condition groups can be nested at most :count levels deep.', ['count' => self::MAX_DEPTH]);
        }

        if (! in_array($group['match'] ?? 'all', ['all', 'any'], true) || ! is_array($group['conditions'] ?? [])) {
            return __('The conditions are invalid.');
        }

        foreach ($group['conditions'] ?? [] as $condition) {
            if (! is_array($condition)) {
                return __('The conditions are invalid.');
            }

            $error = isset($condition['conditions']) ? $this->check($condition, $depth + 1) : $this->checkCondition($condition);

            if ($error !== null) {
                return $error;
            }
        }

        return null;
    }

    /**
     * @param  array<mixed>  $condition
     */
    private function checkCondition(array $condition): ?string
    {
        if (++$this->count > self::MAX_CONDITIONS) {
            return __('Use at most :count conditions.', ['count' => self::MAX_CONDITIONS]);
        }

        $field = $condition['field'] ?? null;
        $operator = ConditionOperator::tryFrom(is_string($condition['operator'] ?? null) ? $condition['operator'] : '');
        $value = $condition['value'] ?? '';

        if (! is_string($field) || ! FieldResolver::isKnown($field)) {
            return __('Choose a field for every condition.');
        }

        if ($operator === null) {
            return __('Choose an operator for every condition.');
        }

        if (! is_scalar($value)) {
            return __('The conditions are invalid.');
        }

        if (mb_strlen((string) $value) > 1000) {
            return __('Condition values may not be longer than :count characters.', ['count' => 1000]);
        }

        if ($operator === ConditionOperator::Matches && ! ConditionEvaluator::isValidPattern((string) $value)) {
            return __('":pattern" is not a valid pattern.', ['pattern' => (string) $value]);
        }

        return null;
    }
}
