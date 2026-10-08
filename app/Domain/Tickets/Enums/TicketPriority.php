<?php

namespace App\Domain\Tickets\Enums;

enum TicketPriority: string
{
    case Low = 'low';
    case Normal = 'normal';
    case High = 'high';
    case Urgent = 'urgent';

    public function label(): string
    {
        return match ($this) {
            self::Low => __('Low'),
            self::Normal => __('Normal'),
            self::High => __('High'),
            self::Urgent => __('Urgent'),
        };
    }

    /**
     * Numeric weight used for sorting (higher is more urgent).
     */
    public function weight(): int
    {
        return match ($this) {
            self::Low => 1,
            self::Normal => 2,
            self::High => 3,
            self::Urgent => 4,
        };
    }
}
