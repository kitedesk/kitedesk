<?php

namespace App\Domain\Mail\Support;

use App\Domain\Branding\Branding;
use App\Domain\Tickets\Models\Ticket;
use App\Domain\Tickets\Support\GuestAccess;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Fills {{placeholders}} in email templates.
 */
class TemplateRenderer
{
    /**
     * @return array<string, string>
     */
    public static function variables(Ticket $ticket, User $recipient, ?User $author = null): array
    {
        $requester = $ticket->requester;

        return [
            'ticket.id' => (string) $ticket->id,
            'ticket.number' => $ticket->reference(),
            'ticket.subject' => $ticket->subject,
            'ticket.status' => $ticket->status->label(),
            'ticket.url' => $recipient->isStaff() ? route('agent.tickets.show', $ticket) : GuestAccess::urlFor($ticket, $recipient),
            'requester.name' => $requester->name,
            'requester.first_name' => Str::before($requester->name, ' '),
            'requester.email' => $requester->email,
            'recipient.name' => $recipient->name,
            'author.name' => $author !== null ? $author->name : __('Support'),
            'app.name' => Branding::current()->name(),
        ];
    }

    /**
     * Plain-text rendering (subjects).
     *
     * @param  array<string, string>  $variables
     */
    public static function text(string $template, array $variables): string
    {
        $text = (string) preg_replace_callback(
            '/\{\{\s*([a-z_.]+)\s*\}\}/i',
            fn (array $matches): string => $variables[$matches[1]] ?? $matches[0],
            $template,
        );

        return trim((string) preg_replace('/\s+/', ' ', $text));
    }

    /**
     * HTML rendering of a plain-text template: escaped, line breaks kept, links clickable.
     *
     * @param  array<string, string>  $variables
     */
    public static function html(string $template, array $variables): string
    {
        $html = (string) preg_replace_callback(
            '/\{\{\s*([a-z_.]+)\s*\}\}/i',
            fn (array $matches): string => e($variables[$matches[1]] ?? $matches[0]),
            e($template),
        );

        $html = (string) preg_replace('~(https?://[^\s<]+)~', '<a href="$1">$1</a>', $html);

        return collect(preg_split('/\R{2,}/', trim($html)) ?: [])
            ->map(fn (string $paragraph): string => '<p>'.nl2br($paragraph, false).'</p>')
            ->implode('');
    }
}
