<?php

namespace App\Domain\Jira\Jobs;

use App\Domain\Jira\Actions\ImportComment;
use App\Domain\Jira\Actions\SyncIssue;
use App\Domain\Jira\Models\JiraIssueLink;
use App\Domain\Jira\Support\JiraClient;
use App\Domain\Jira\Support\JiraModule;
use App\Domain\Tickets\Actions\AddMessage;
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

    public function handle(SyncIssue $syncIssue, ImportComment $importComment, AddMessage $addMessage): void
    {
        $issue = is_array($this->payload['issue'] ?? null) ? $this->payload['issue'] : null;

        if (! JiraModule::isEnabled() || $issue === null || ! isset($issue['id'], $issue['key'])) {
            return;
        }

        $links = JiraIssueLink::query()->with('ticket')->where('issue_id', (string) $issue['id'])->get();

        foreach ($links as $link) {
            match ($this->payload['webhookEvent'] ?? null) {
                'jira:issue_updated' => $syncIssue->handle($link, JiraClient::make()->normalize($issue)),
                'jira:issue_deleted' => $this->deleted($link, $addMessage),
                'comment_created' => is_array($this->payload['comment'] ?? null) ? $importComment->handle($link, $this->payload['comment']) : null,
                default => null,
            };
        }
    }

    private function deleted(JiraIssueLink $link, AddMessage $addMessage): void
    {
        if ($link->deleted_at_jira !== null) {
            return;
        }

        $link->update(['deleted_at_jira' => now()]);

        $addMessage->handle(
            $link->ticket,
            null,
            '<p>'.e(__('Jira :key was deleted in Jira.', ['key' => $link->issue_key])).'</p>',
            isInternal: true,
            metadata: ['jira' => ['issue_key' => $link->issue_key, 'kind' => 'status']],
        );
    }
}
