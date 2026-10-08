<?php

namespace App\Domain\Tickets\Actions;

use App\Domain\Sla\SlaTracker;
use App\Domain\Support\RichText;
use App\Domain\Tickets\Enums\TicketChannel;
use App\Domain\Tickets\Enums\TicketStatus;
use App\Domain\Tickets\Events\MessageCreated;
use App\Domain\Tickets\Models\CustomStatus;
use App\Domain\Tickets\Models\Ticket;
use App\Domain\Tickets\Models\TicketMessage;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Adds a public reply or internal note to a ticket and applies the resulting
 * status change and SLA bookkeeping.
 */
class AddMessage
{
    public function __construct(private SlaTracker $sla, private UpdateTicket $updateTicket) {}

    /**
     * @param  list<UploadedFile>  $attachments
     * @param  User|null  $author  Null for messages written by the system (workflows); they count as staff messages.
     * @param  TicketStatus|CustomStatus|null  $statusAfter  Status chosen by an agent ("submit as ..."), as a category or an admin-defined status; defaults are applied when null.
     * @param  array<string, mixed>  $metadata
     * @param  list<string>  $secretTokens  Secrets on this ticket, not sent yet, to send with the reply.
     */
    public function handle(
        Ticket $ticket,
        ?User $author,
        string $body,
        bool $isInternal = false,
        TicketChannel $channel = TicketChannel::Agent,
        array $attachments = [],
        TicketStatus|CustomStatus|null $statusAfter = null,
        array $metadata = [],
        array $secretTokens = [],
    ): TicketMessage {
        return DB::transaction(function () use ($ticket, $author, $body, $isInternal, $channel, $attachments, $statusAfter, $metadata, $secretTokens): TicketMessage {
            $message = $ticket->messages()->create([
                'author_id' => $author?->id,
                'body' => RichText::sanitize($body, keepMentions: $isInternal && $this->isStaff($author)),
                'is_internal' => $isInternal,
                'channel' => $channel,
                'metadata' => $metadata === [] ? null : $metadata,
            ]);

            foreach ($attachments as $attachment) {
                $message->addMedia($attachment)->toMediaCollection('attachments');
            }

            if ($secretTokens !== []) {
                $ticket->secrets()->whereIn('token', $secretTokens)->whereNull('ticket_message_id')->update(['ticket_message_id' => $message->id]);
            }

            $ticket->loadMissing('slaPolicy.businessSchedule.holidays');

            if (! $isInternal) {
                $this->isStaff($author)
                    ? $this->recordStaffReply($ticket)
                    : $this->recordCustomerReply($ticket);
            }

            $status = $statusAfter ?? $this->defaultStatusAfter($ticket, $author, $isInternal);

            if ($status instanceof CustomStatus && $status->id !== $ticket->ticket_status_id) {
                $this->updateTicket->handle($ticket, ['ticket_status_id' => $status->id], $author);
            } elseif ($status instanceof TicketStatus && $status !== $ticket->status) {
                $this->updateTicket->handle($ticket, ['status' => $status], $author);
            } else {
                $ticket->save();
            }

            MessageCreated::dispatch($message);

            return $message;
        });
    }

    private function recordStaffReply(Ticket $ticket): void
    {
        $ticket->first_responded_at ??= now();
        $ticket->last_agent_reply_at = now();
        $this->sla->recordStaffReply($ticket);
    }

    private function recordCustomerReply(Ticket $ticket): void
    {
        $ticket->last_customer_reply_at = now();
        $this->sla->recordCustomerReply($ticket);
    }

    private function isStaff(?User $author): bool
    {
        return $author === null || $author->isStaff();
    }

    private function defaultStatusAfter(Ticket $ticket, ?User $author, bool $isInternal): ?TicketStatus
    {
        if ($isInternal) {
            return null;
        }

        if ($this->isStaff($author)) {
            return $ticket->status === TicketStatus::New ? TicketStatus::Open : null;
        }

        return in_array($ticket->status, [TicketStatus::Pending, TicketStatus::Solved], true)
            ? TicketStatus::Open
            : null;
    }
}
