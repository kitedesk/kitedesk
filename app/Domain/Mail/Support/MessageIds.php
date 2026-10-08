<?php

namespace App\Domain\Mail\Support;

use App\Domain\Tickets\Models\Ticket;
use App\Domain\Tickets\Models\TicketMessage;
use Illuminate\Support\Str;

/**
 * Message-IDs for outgoing email. Every id we generate starts with "ticket-{id}-{signature}", so a
 * reply's In-Reply-To / References header leads back to the ticket even if the message wasn't
 * stored, and nobody can make up an id that points at someone else's ticket.
 *
 * Host and key are read on every call (`kitedesk.mail.message_id_*`), so the hosted edition can
 * give each workspace its own and a reply can't be steered into another workspace's ticket.
 */
class MessageIds
{
    public static function host(): string
    {
        return config('kitedesk.mail.message_id_host') ?: parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'kitedesk.local';
    }

    /**
     * The root of a ticket's email thread.
     */
    public static function forTicket(Ticket $ticket): string
    {
        return self::prefix($ticket->id).'@'.self::host();
    }

    /**
     * The id of a message: the original Message-ID for mail we received, else a generated one.
     */
    public static function forMessage(TicketMessage $message): string
    {
        return $message->email_message_id ?? self::prefix($message->ticket_id).".message-{$message->id}@".self::host();
    }

    /**
     * A unique id for an automatic email (auto-reply, solved notice…).
     */
    public static function unique(Ticket $ticket, string $kind): string
    {
        return self::prefix($ticket->id).".{$kind}.".Str::lower(Str::random(12)).'@'.self::host();
    }

    /**
     * The ticket id encoded in one of our generated Message-IDs, if its signature is valid.
     */
    public static function ticketIdFrom(string $messageId): ?int
    {
        $host = preg_quote(self::host(), '/');

        if (preg_match('/^ticket-(\d+)-([0-9a-f]{16})(?:\.[^@]*)?@'.$host.'$/i', $messageId, $matches) !== 1) {
            return null;
        }

        return hash_equals(self::signature((int) $matches[1]), Str::lower($matches[2])) ? (int) $matches[1] : null;
    }

    private static function prefix(int $ticketId): string
    {
        return "ticket-{$ticketId}-".self::signature($ticketId);
    }

    private static function signature(int $ticketId): string
    {
        return substr(hash_hmac('sha256', "ticket-{$ticketId}", (string) (config('kitedesk.mail.message_id_key') ?: config('app.key'))), 0, 16);
    }

    /**
     * Ids of the ticket's public messages before the given one, for the References header.
     *
     * @return list<string>
     */
    public static function thread(Ticket $ticket, ?TicketMessage $before = null): array
    {
        $messages = $ticket->messages()
            ->public()
            ->when($before !== null, fn ($query) => $query->where('id', '<', $before?->id))
            ->latest('id')
            ->limit(10)
            ->get(['id', 'ticket_id', 'email_message_id'])
            ->reverse()
            ->map(fn (TicketMessage $message): string => self::forMessage($message));

        return array_values(array_unique([self::forTicket($ticket), ...$messages]));
    }
}
