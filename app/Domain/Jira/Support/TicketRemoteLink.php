<?php

namespace App\Domain\Jira\Support;

use App\Domain\Tickets\Models\Ticket;

/**
 * How a ticket appears in a Jira issue's "Web links".
 */
final class TicketRemoteLink
{
    /**
     * Stable per installation and ticket, so posting it again updates the same link.
     */
    public static function globalId(Ticket $ticket): string
    {
        $host = parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'kitedesk';

        return "kitedesk:{$host}:ticket:{$ticket->id}";
    }

    public static function title(Ticket $ticket): string
    {
        return "KiteDesk {$ticket->reference()}: {$ticket->subject}";
    }

    public static function url(Ticket $ticket): string
    {
        return route('agent.tickets.show', $ticket);
    }

    public static function push(JiraClient $client, string $issueKey, Ticket $ticket): void
    {
        $client->upsertRemoteLink($issueKey, self::globalId($ticket), self::url($ticket), self::title($ticket), ! $ticket->status->isUnresolved());
    }
}
