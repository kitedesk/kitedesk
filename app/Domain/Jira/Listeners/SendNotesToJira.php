<?php

namespace App\Domain\Jira\Listeners;

use App\Domain\Jira\Jobs\PostCommentToJira;
use App\Domain\Jira\Models\JiraIssueLink;
use App\Domain\Jira\Support\JiraModule;
use App\Domain\Tickets\Events\MessageCreated;

/**
 * Internal notes on a ticket with linked issues go to those issues as comments. Replies to
 * the customer never do, and neither do the notes that came from Jira in the first place.
 */
class SendNotesToJira
{
    public function handle(MessageCreated $event): void
    {
        $message = $event->message;

        if (! $message->is_internal || isset($message->metadata['jira']) || ! JiraModule::isEnabled()) {
            return;
        }

        JiraIssueLink::query()
            ->where('ticket_id', $message->ticket_id)
            ->whereNull('deleted_at_jira')
            ->get()
            ->each(fn (JiraIssueLink $link) => PostCommentToJira::dispatch($link, $message));
    }
}
