<?php

namespace App\Domain\Tickets\Enums;

enum TicketStatus: string
{
    case New = 'new';
    case Open = 'open';
    case Pending = 'pending';
    case OnHold = 'on_hold';
    case Solved = 'solved';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::New => __('New'),
            self::Open => __('Open'),
            self::Pending => __('Pending'),
            self::OnHold => __('On-hold'),
            self::Solved => __('Solved'),
            self::Closed => __('Closed'),
        };
    }

    /**
     * Unresolved tickets still need work from the support team or the customer.
     */
    public function isUnresolved(): bool
    {
        return ! in_array($this, [self::Solved, self::Closed], true);
    }

    /**
     * Statuses in which the resolution SLA clock is paused (waiting on someone else).
     */
    public function pausesSla(): bool
    {
        return in_array($this, [self::Pending, self::OnHold], true);
    }

    /**
     * @return list<self>
     */
    public static function unresolved(): array
    {
        return array_values(array_filter(self::cases(), fn (self $status): bool => $status->isUnresolved()));
    }
}
