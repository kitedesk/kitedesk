<?php

namespace App\Domain\Jira\Listeners;

use App\Domain\Jira\Jobs\SyncRemoteLinks;
use App\Domain\Jira\Models\JiraIssueLink;
use App\Domain\Jira\Support\JiraModule;
use App\Domain\Tickets\Enums\TicketStatus;
use App\Domain\Tickets\Events\TicketUpdated;

/**
 * Keeps the ticket's entry on its Jira issues current when its subject changes or it is
 * solved or reopened.
 */
class UpdateTicketRemoteLinks
{
    public function handle(TicketUpdated $event): void
    {
        $changes = $event->changes;
        $resolvedChanged = isset($changes['status'])
            && $this->isResolved($changes['status']['from']) !== $this->isResolved($changes['status']['to']);

        if ((! isset($changes['subject']) && ! $resolvedChanged) || ! JiraModule::isEnabled()) {
            return;
        }

        if (JiraIssueLink::query()->where('ticket_id', $event->ticket->id)->whereNull('deleted_at_jira')->exists()) {
            SyncRemoteLinks::dispatch($event->ticket);
        }
    }

    private function isResolved(mixed $status): bool
    {
        $status = TicketStatus::tryFrom((string) $status);

        return $status !== null && ! $status->isUnresolved();
    }
}
