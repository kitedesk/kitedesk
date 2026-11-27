<?php

namespace App\Domain\Jira\Actions;

use App\Domain\Jira\Exceptions\JiraException;
use App\Domain\Jira\Models\JiraIssueLink;
use App\Domain\Jira\Support\JiraClient;
use App\Domain\Jira\Support\TicketRemoteLink;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * Removes the link, and the ticket from the issue's links when Jira can be reached.
 */
class UnlinkIssue
{
    public function handle(JiraIssueLink $link, ?User $user): void
    {
        $ticket = $link->ticket;
        $link->delete();

        activity()->performedOn($ticket)->causedBy($user)->event('jira_unlinked')->withProperties(['jira' => $link->issue_key])->log('jira_unlinked');

        if ($link->deleted_at_jira !== null) {
            return;
        }

        try {
            JiraClient::make()->deleteRemoteLink($link->issue_key, TicketRemoteLink::globalId($ticket));
        } catch (JiraException $exception) {
            Log::info('Could not remove the ticket from the Jira issue\'s links.', ['issue' => $link->issue_key, 'exception' => $exception->getMessage()]);
        }
    }
}
