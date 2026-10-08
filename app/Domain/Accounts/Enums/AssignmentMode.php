<?php

namespace App\Domain\Accounts\Enums;

/**
 * How a group hands out new tickets that nobody is assigned to yet.
 */
enum AssignmentMode: string
{
    case Manual = 'manual';
    case RoundRobin = 'round_robin';
    case LeastBusy = 'least_busy';

    public function label(): string
    {
        return match ($this) {
            self::Manual => __('Manual'),
            self::RoundRobin => __('Round robin'),
            self::LeastBusy => __('Least busy agent'),
        };
    }
}
