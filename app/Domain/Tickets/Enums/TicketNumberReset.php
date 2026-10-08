<?php

namespace App\Domain\Tickets\Enums;

use Carbon\CarbonInterface;

/**
 * When the {seq} counter of ticket numbers starts again from 1.
 */
enum TicketNumberReset: string
{
    case Never = 'never';
    case Yearly = 'yearly';
    case Monthly = 'monthly';

    public function label(): string
    {
        return match ($this) {
            self::Never => __('Never'),
            self::Yearly => __('Every year'),
            self::Monthly => __('Every month'),
        };
    }

    /**
     * The counter a ticket created at this moment draws from.
     */
    public function scope(CarbonInterface $at): string
    {
        return match ($this) {
            self::Never => 'all',
            self::Yearly => $at->format('Y'),
            self::Monthly => $at->format('Y-m'),
        };
    }
}
