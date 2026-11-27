<?php

namespace App\Domain\Jira\Jobs;

use App\Domain\Jira\Actions\ImportComment;
use App\Domain\Jira\Actions\MarkIssueDeleted;
use App\Domain\Jira\Actions\SyncIssue;
use App\Domain\Jira\Models\JiraIssueLink;
use App\Domain\Jira\Support\JiraClient;
use App\Domain\Jira\Support\JiraModule;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Applies one verified Jira webhook delivery to the tickets linked to its issue.
 */
class ProcessJiraWebhook implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(public array $payload) {}

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [10, 60];
    }

    public function handle(SyncIssue $syncIssue, ImportComment $importComment, MarkIssueDeleted $markIssueDeleted): void
    {
        $issue = is_array($this->payload['issue'] ?? null) ? $this->payload['issue'] : null;

        if (! JiraModule::isEnabled() || $issue === null || ! isset($issue['id'], $issue['key'])) {
            return;
        }

        $links = JiraIssueLink::query()->with('ticket')->where('issue_id', (string) $issue['id'])->get();

        foreach ($links as $link) {
            match ($this->payload['webhookEvent'] ?? null) {
                'jira:issue_updated' => $syncIssue->handle($link, JiraClient::make()->normalize($issue)),
                'jira:issue_deleted' => $markIssueDeleted->handle($link),
                'comment_created' => is_array($this->payload['comment'] ?? null) ? $importComment->handle($link, $this->payload['comment']) : null,
                default => null,
            };
        }
    }
}
