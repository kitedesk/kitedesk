<?php

namespace App\Domain\Workflows\Conditions;

use App\Domain\Workflows\Engine\Placeholders;
use App\Domain\Workflows\Engine\RunContext;
use App\Domain\Workflows\Enums\ConditionOperator;
use Illuminate\Support\Str;

/**
 * Evaluates condition groups for If, Filter and Switch nodes.
 *
 * A group is `{match: "all"|"any", conditions: list<condition|group>}` and a condition is
 * `{field, operator, value}`. Values may use placeholders (`{{vars.limit}}`, `{{item.email}}`),
 * so a field can be compared with a variable, a loop item or another ticket field.
 * Comparisons ignore case. On list fields (tags, categories...), "is" means "includes".
 */
class ConditionEvaluator
{
    public function __construct(private FieldResolver $fields, private Placeholders $placeholders) {}

    /**
     * @param  array<string, mixed>  $group
     */
    public function passes(array $group, RunContext $context): bool
    {
        $conditions = is_array($group['conditions'] ?? null) ? $group['conditions'] : [];

        if ($conditions === []) {
            return true;
        }

        $any = ($group['match'] ?? 'all') === 'any';

        foreach ($conditions as $condition) {
            $holds = isset($condition['conditions'])
                ? $this->passes($condition, $context)
                : $this->holds($condition, $context);

            if ($any && $holds) {
                return true;
            }

            if (! $any && ! $holds) {
                return false;
            }
        }

        return ! $any;
    }

    /**
     * @param  array<string, mixed>  $condition
     */
    public function holds(array $condition, RunContext $context): bool
    {
        $operator = ConditionOperator::tryFrom((string) ($condition['operator'] ?? ''));

        if ($operator === null) {
            return false;
        }

        $actual = $this->fields->value((string) ($condition['field'] ?? ''), $context);
        $expected = $this->placeholders->text((string) ($condition['value'] ?? ''), $context);

        return $this->compare($actual, $operator, $expected);
    }

    public function compare(mixed $actual, ConditionOperator $operator, string $expected): bool
    {
        $actual = FieldResolver::normalize($actual);
        $pattern = trim($expected);
        $expected = mb_strtolower($pattern);

        if (is_array($actual)) {
            return $this->compareList($actual, $operator, $expected, $pattern);
        }

        return match ($operator) {
            ConditionOperator::Is => $actual === $expected,
            ConditionOperator::IsNot => $actual !== $expected,
            ConditionOperator::Contains => $expected !== '' && Str::contains($actual, $expected),
            ConditionOperator::NotContains => $expected === '' || ! Str::contains($actual, $expected),
            ConditionOperator::StartsWith => $expected !== '' && Str::startsWith($actual, $expected),
            ConditionOperator::Matches => self::matchesPattern($pattern, $actual),
            ConditionOperator::GreaterThan => is_numeric($actual) && is_numeric($expected) && (float) $actual > (float) $expected,
            ConditionOperator::LessThan => is_numeric($actual) && is_numeric($expected) && (float) $actual < (float) $expected,
            ConditionOperator::IsEmpty => $actual === '',
            ConditionOperator::IsSet => $actual !== '',
        };
    }

    /**
     * @param  list<string>  $actual
     */
    private function compareList(array $actual, ConditionOperator $operator, string $expected, string $pattern): bool
    {
        $some = fn (callable $test): bool => collect($actual)->contains($test);

        return match ($operator) {
            ConditionOperator::Is => in_array($expected, $actual, true),
            ConditionOperator::IsNot => ! in_array($expected, $actual, true),
            ConditionOperator::Contains => $expected !== '' && $some(fn (string $item): bool => Str::contains($item, $expected)),
            ConditionOperator::NotContains => $expected === '' || ! $some(fn (string $item): bool => Str::contains($item, $expected)),
            ConditionOperator::StartsWith => $expected !== '' && $some(fn (string $item): bool => Str::startsWith($item, $expected)),
            ConditionOperator::Matches => $some(fn (string $item): bool => self::matchesPattern($pattern, $item)),
            ConditionOperator::GreaterThan => is_numeric($expected) && count($actual) > (float) $expected,
            ConditionOperator::LessThan => is_numeric($expected) && count($actual) < (float) $expected,
            ConditionOperator::IsEmpty => $actual === [],
            ConditionOperator::IsSet => $actual !== [],
        };
    }

    /**
     * Whether the text matches the (case-insensitive) pattern. Invalid patterns never match.
     */
    public static function matchesPattern(string $pattern, string $subject): bool
    {
        return @preg_match(self::delimit($pattern), $subject) === 1;
    }

    public static function isValidPattern(string $pattern): bool
    {
        return @preg_match(self::delimit($pattern), '') !== false;
    }

    private static function delimit(string $pattern): string
    {
        return '~'.str_replace('~', '\~', $pattern).'~iu';
    }
}
