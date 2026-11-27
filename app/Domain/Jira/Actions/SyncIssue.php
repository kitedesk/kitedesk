<?php

namespace App\Domain\Jira\Actions;

use App\Domain\Jira\Models\JiraIssueLink;
use App\Domain\Jira\Support\JiraClient;
use App\Domain\Tickets\Actions\AddMessage;
use App\Models\User;

/**
 * Takes in what Jira says an issue looks like now (from a webhook or the periodic refresh).
 * When its status changed, the ticket gets an internal note and the status mapping runs.
 *
 * @phpstan-import-type Issue from JiraClient
 */
class SyncIssue
{
    public function __construct(private AddMessage $addMessage, private ApplyStatusMapping $applyStatusMapping) {}

    /**
     * @param  Issue  $issue
     * @param  User|null  $actor  the agent who changed the status from KiteDesk, named in the note
     */
    public function handle(JiraIssueLink $link, array $issue, ?User $actor = null): void
    {
        $previousStatus = $link->status_name;

        $link->fill([
            'issue_key' => $issue['key'],
            'summary' => $issue['summary'],
            'issue_type' => $issue['issue_type'],
            'status_id' => $issue['status_id'],
            'status_name' => $issue['status_name'],
            'status_category' => $issue['status_category'],
            'resolution' => $issue['resolution'],
            'assignee_name' => $issue['assignee_name'],
            'url' => $issue['url'],
            'last_synced_at' => now(),
        ]);

        $statusChanged = $link->isDirty('status_name') && $issue['status_name'] !== null;
        $link->save();

        if (! $statusChanged) {
            return;
        }

        $this->addMessage->handle(
            $link->ticket,
            null,
            '<p>'.e($actor !== null
                ? __(':name moved Jira :key from :from to :to.', [
                    'name' => $actor->name,
                    'key' => $link->issue_key,
                    'from' => $previousStatus ?? '—',
                    'to' => $issue['status_name'],
                ])
                : __('Jira :key moved from :from to :to.', [
                    'key' => $link->issue_key,
                    'from' => $previousStatus ?? '—',
                    'to' => $issue['status_name'],
                ])).($issue['resolution'] !== null ? ' '.e(__('Resolution: :resolution.', ['resolution' => $issue['resolution']])) : '').'</p>',
            isInternal: true,
            metadata: ['jira' => ['issue_key' => $link->issue_key, 'kind' => 'status']],
        );

        $this->applyStatusMapping->handle($link->ticket->refresh(), $issue['status_id'], $issue['status_category']);
    }
}
