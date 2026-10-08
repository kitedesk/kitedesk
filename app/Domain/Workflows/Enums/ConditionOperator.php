<?php

namespace App\Domain\Workflows\Enums;

enum ConditionOperator: string
{
    case Is = 'is';
    case IsNot = 'is_not';
    case Contains = 'contains';
    case NotContains = 'not_contains';
    case StartsWith = 'starts_with';
    case Matches = 'matches';
    case GreaterThan = 'greater_than';
    case LessThan = 'less_than';
    case IsEmpty = 'is_empty';
    case IsSet = 'is_set';

    public function label(): string
    {
        return match ($this) {
            self::Is => __('is'),
            self::IsNot => __('is not'),
            self::Contains => __('contains'),
            self::NotContains => __('does not contain'),
            self::StartsWith => __('starts with'),
            self::Matches => __('matches pattern'),
            self::GreaterThan => __('is greater than'),
            self::LessThan => __('is less than'),
            self::IsEmpty => __('is empty'),
            self::IsSet => __('is not empty'),
        };
    }

    /**
     * Operators that don't compare against a value.
     */
    public function isUnary(): bool
    {
        return $this === self::IsEmpty || $this === self::IsSet;
    }
}
