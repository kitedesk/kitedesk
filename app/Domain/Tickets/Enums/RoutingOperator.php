<?php

namespace App\Domain\Tickets\Enums;

enum RoutingOperator: string
{
    case Is = 'is';
    case IsNot = 'is_not';
    case Contains = 'contains';

    public function label(): string
    {
        return match ($this) {
            self::Is => __('is'),
            self::IsNot => __('is not'),
            self::Contains => __('contains'),
        };
    }
}
