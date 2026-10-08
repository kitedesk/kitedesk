<?php

namespace App\Domain\Ai\Support;

use App\Domain\Support\RichText;
use App\Domain\Tickets\Models\Ticket;
use App\Domain\Tickets\Models\TicketMessage;
use App\Domain\Tickets\Support\CustomStatuses;
use Illuminate\Support\Collection;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * A ticket and its recent conversation as plain text, for an AI model (the agent assistant) or
 * an MCP client. Internal notes are included and labelled: only staff ever see this. Secret
 * contents and attachment files are never included, only attachment names.
 */
final class TicketContext
{
    /**
     * Roughly 12k tokens; the oldest messages are dropped first to stay under it.
     */
    public const int MAX_CHARACTERS = 48_000;

    public static function for(Ticket $ticket, int $messages = AiSettings::DEFAULT_CONTEXT_MESSAGES): string
    {
        $ticket->loadMissing(['requester', 'assignee', 'group', 'organization', 'category', 'tags']);

        $header = collect([
            'Ticket: '.$ticket->reference().' — '.$ticket->subject,
            'Status: '.(CustomStatuses::find($ticket->ticket_status_id)->name ?? $ticket->status->label()),
            'Priority: '.$ticket->priority->label(),
            'Requester: '.$ticket->requester->name.($ticket->organization ? ' ('.$ticket->organization->name.')' : ''),
            $ticket->assignee ? 'Assignee: '.$ticket->assignee->name : null,
            $ticket->group ? 'Group: '.$ticket->group->name : null,
            $ticket->category ? 'Category: '.$ticket->category->name : null,
            $ticket->tags->isNotEmpty() ? 'Tags: '.$ticket->tags->pluck('name')->join(', ') : null,
        ])->filter()->join("\n");

        $budget = self::MAX_CHARACTERS - mb_strlen($header);
        $kept = [];

        foreach (self::recentMessages($ticket, $messages)->reverse() as $message) {
            $text = self::message($message, $ticket);
            $budget -= mb_strlen($text);

            if ($budget < 0 && $kept !== []) {
                break;
            }

            $kept[] = $text;
        }

        return $header."\n\nConversation (oldest first):\n\n".implode("\n\n", array_reverse($kept));
    }

    /**
     * The latest customer-visible message from the requester or a CC: what a search for related
     * articles should match.
     */
    public static function latestCustomerMessage(Ticket $ticket): ?string
    {
        $message = $ticket->messages()->public()->where('author_id', '!=', null)
            ->whereHas('author', fn ($author) => $author->customers())
            ->latest()->latest('id')->first();

        return $message ? RichText::toPlainText($message->body) : null;
    }

    /**
     * @return Collection<int, TicketMessage>
     */
    private static function recentMessages(Ticket $ticket, int $limit): Collection
    {
        return $ticket->messages()->with(['author', 'media'])
            ->latest()->latest('id')->limit($limit)->get()->reverse()->values();
    }

    private static function message(TicketMessage $message, Ticket $ticket): string
    {
        $author = $message->author;
        $label = match (true) {
            $message->is_internal => 'Internal note by '.($author->name ?? 'automation').' (not visible to the customer)',
            $author === null => 'Automated message',
            $author->isStaff() => 'Agent '.$author->name,
            $author->id === $ticket->requester_id => 'Customer '.$author->name,
            default => 'CC '.$author->name,
        };

        $attachments = $message->getMedia('attachments')
            ->map(fn (Media $media): string => '(attachment: '.$media->file_name.')')
            ->join("\n");

        return '['.$label.', '.$message->created_at?->toDayDateTimeString().']'."\n"
            .RichText::toPlainText($message->body)
            .($attachments !== '' ? "\n".$attachments : '');
    }
}
