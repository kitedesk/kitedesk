<?php

namespace App\Domain\Tickets\Support;

use App\Domain\Tickets\Models\Ticket;
use App\Domain\Tickets\Models\TicketMessage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * The messages shown on a ticket page: the most recent ones, oldest first, unless the reader
 * asked for the whole conversation. Long email threads would otherwise be sent in full on
 * every visit and realtime reload.
 */
class TicketThread
{
    public const int RECENT = 50;

    /**
     * @return Collection<int, TicketMessage>
     */
    public static function messages(Ticket $ticket, bool $publicOnly, bool $everything = false): Collection
    {
        $query = self::query($ticket, $publicOnly)->with(['author', 'media', 'secrets.creator', 'secrets.ticket']);

        if ($everything) {
            return $query->oldest()->oldest('id')->get();
        }

        return $query->latest()->latest('id')->limit(self::RECENT)->get()->reverse()->values();
    }

    /**
     * How many messages come before the ones shown.
     */
    public static function earlierCount(Ticket $ticket, bool $publicOnly, bool $everything = false): int
    {
        return $everything ? 0 : max(0, self::query($ticket, $publicOnly)->count() - self::RECENT);
    }

    /**
     * @return Builder<TicketMessage>
     */
    private static function query(Ticket $ticket, bool $publicOnly): Builder
    {
        return $ticket->messages()->getQuery()->when($publicOnly, fn (Builder $query) => $query->public());
    }
}
