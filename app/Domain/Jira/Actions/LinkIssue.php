<?php

namespace App\Domain\Jira\Actions;

use App\Domain\Jira\Exceptions\JiraException;
use App\Domain\Jira\Models\JiraIssueLink;
use App\Domain\Jira\Support\JiraClient;
use App\Domain\Jira\Support\TicketRemoteLink;
use App\Domain\Tickets\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * Links a ticket to a Jira issue and shows the ticket on the issue. Linking the same issue
 * again only refreshes it.
 *
 * @phpstan-import-type Issue from JiraClient
 */
class LinkIssue
{
    /**
     * @param  Issue|null  $issue  the issue when the caller already has it (e.g. just created)
     *
     * @throws JiraException when the issue can't be read
     */
    public function handle(Ticket $ticket, string $keyOrId, ?User $user, ?array $issue = null): JiraIssueLink
    {
        $client = JiraClient::make();
        $issue ??= $client->issue($keyOrId);

        $link = JiraIssueLink::query()->firstOrNew(['ticket_id' => $ticket->id, 'issue_id' => $issue['id']]);
        $isNew = ! $link->exists;

        $link->fill([
            'issue_key' => $issue['key'],
            'summary' => $issue['summary'],
            'issue_type' => $issue['issue_type'],
            'status_name' => $issue['status_name'],
            'status_category' => $issue['status_category'],
            'resolution' => $issue['resolution'],
            'assignee_name' => $issue['assignee_name'],
            'url' => $issue['url'],
            'deleted_at_jira' => null,
            'last_synced_at' => now(),
        ]);

        if ($isNew) {
            $link->linked_by = $user?->id;
        }

        $link->save();

        if ($isNew) {
            activity()->performedOn($ticket)->causedBy($user)->event('jira_linked')->withProperties(['jira' => $issue['key']])->log('jira_linked');
        }

        try {
            TicketRemoteLink::push($client, $issue['key'], $ticket);
        } catch (JiraException $exception) {
            // The link works without it; the account may just lack the "Link issues" permission.
            Log::info('Could not add the ticket to the Jira issue\'s links.', ['issue' => $issue['key'], 'exception' => $exception->getMessage()]);
        }

        return $link;
    }
}
