<?php

namespace App\Domain\Jira\Actions;

use App\Domain\Jira\Enums\StatusCategory;
use App\Domain\Jira\Models\JiraIssueLink;
use App\Domain\Jira\Support\JiraSettings;
use App\Domain\Tickets\Actions\UpdateTicket;
use App\Domain\Tickets\Enums\TicketStatus;
use App\Domain\Tickets\Models\Ticket;
use App\Domain\Tickets\Support\CustomStatuses;

/**
 * Moves a ticket to the status the admin mapped to the Jira status category one of its issues
 * just moved into. "Done" only counts once every issue linked to the ticket is done, and
 * closed tickets are never touched.
 */
class ApplyStatusMapping
{
    public function __construct(private UpdateTicket $updateTicket) {}

    public function handle(Ticket $ticket, StatusCategory $category): void
    {
        $statusId = JiraSettings::current()->statusFor($category);
        $status = CustomStatuses::find($statusId);

        if ($status === null || ! $status->is_active || $ticket->status === TicketStatus::Closed || $ticket->ticket_status_id === $status->id) {
            return;
        }

        if ($category === StatusCategory::Done && ! $this->allDone($ticket)) {
            return;
        }

        $this->updateTicket->handle($ticket, ['ticket_status_id' => $status->id]);
    }

    private function allDone(Ticket $ticket): bool
    {
        return JiraIssueLink::query()
            ->where('ticket_id', $ticket->id)
            ->whereNull('deleted_at_jira')
            ->get()
            ->every(fn (JiraIssueLink $link): bool => $link->isDone());
    }
}
