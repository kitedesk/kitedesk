<?php

namespace App\Domain\Jira\Actions;

use App\Domain\Jira\Models\JiraIssueLink;
use App\Domain\Tickets\Actions\AddMessage;

/**
 * Records that a linked issue no longer exists in Jira, with a note on the ticket. The link
 * stays, so agents still see which issue it was.
 */
class MarkIssueDeleted
{
    public function __construct(private AddMessage $addMessage) {}

    public function handle(JiraIssueLink $link): void
    {
        if ($link->deleted_at_jira !== null) {
            return;
        }

        $link->update(['deleted_at_jira' => now(), 'last_synced_at' => now()]);

        $this->addMessage->handle(
            $link->ticket,
            null,
            '<p>'.e(__('Jira :key was deleted in Jira.', ['key' => $link->issue_key])).'</p>',
            isInternal: true,
            metadata: ['jira' => ['issue_key' => $link->issue_key, 'kind' => 'status']],
        );
    }
}
