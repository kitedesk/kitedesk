<?php

namespace App\Domain\Tickets\Actions;

use App\Domain\Tickets\Models\Ticket;
use App\Domain\Tickets\Models\TicketMessage;
use App\Domain\Webhooks\Enums\WebhookEvent;
use App\Domain\Webhooks\Support\Webhooks;
use App\Http\Resources\Api\V1\TicketResource;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;

/**
 * Permanently removes a ticket with its messages, attachments and history. Tickets merged
 * into it keep existing and simply lose the link.
 */
class DeleteTicket
{
    public function handle(Ticket $ticket): void
    {
        // Built before the ticket is gone, so receivers get its last state.
        $data = Webhooks::subscribed(WebhookEvent::TicketDeleted) ? ['ticket' => (new TicketResource($ticket))->resolve()] : null;

        DB::transaction(function () use ($ticket): void {
            // One by one so the media library also deletes the attachment files.
            $ticket->messages()->each(fn (TicketMessage $message) => $message->delete());

            Activity::query()->forSubject($ticket)->delete();

            $ticket->delete();
        });

        if ($data !== null) {
            Webhooks::send(WebhookEvent::TicketDeleted, $data);
        }
    }
}
