<?php

namespace App\Domain\Jira\Console;

use App\Domain\Jira\Actions\MarkIssueDeleted;
use App\Domain\Jira\Actions\SyncIssue;
use App\Domain\Jira\Exceptions\JiraException;
use App\Domain\Jira\Models\JiraIssueLink;
use App\Domain\Jira\Support\JiraClient;
use App\Domain\Jira\Support\JiraModule;
use App\Domain\Jira\Support\JiraSettings;
use Illuminate\Console\Command;

/**
 * Catches up on linked issues whose webhooks never arrived: every issue not refreshed in the
 * last 15 minutes is read again, 100 at a time. Jira rejects a whole search when one of its
 * issues is gone or hidden, so such a batch is read issue by issue instead, and issues Jira
 * no longer has are marked deleted.
 */
class RefreshJiraIssuesCommand extends Command
{
    protected $signature = 'jira:refresh';

    protected $description = 'Refresh linked Jira issues that webhooks may have missed';

    public function handle(SyncIssue $syncIssue, MarkIssueDeleted $markIssueDeleted): int
    {
        if (! JiraModule::isEnabled() || ! JiraSettings::current()->isConnected()) {
            return self::SUCCESS;
        }

        $client = JiraClient::make();

        $stale = JiraIssueLink::query()
            ->with('ticket')
            ->whereNull('deleted_at_jira')
            ->where(fn ($query) => $query->whereNull('last_synced_at')->orWhere('last_synced_at', '<', now()->subMinutes(15)))
            ->get()
            ->groupBy('issue_id');

        foreach ($stale->chunk(100) as $batch) {
            $ids = $batch->keys()->map(fn (int|string $id): string => (string) (int) $id)->implode(',');

            try {
                $issues = collect($client->search("id in ({$ids})", 100))->keyBy('id');
            } catch (JiraException $exception) {
                if ($exception->status !== 400) {
                    $this->warn($exception->getMessage());

                    return self::FAILURE;
                }

                $issues = null;
            }

            foreach ($batch as $issueId => $links) {
                try {
                    $issue = $issues !== null ? $issues->get((string) $issueId) : $client->issue((string) $issueId);
                } catch (JiraException $exception) {
                    if ($exception->status === 404) {
                        $links->each(fn (JiraIssueLink $link) => $markIssueDeleted->handle($link));

                        continue;
                    }

                    $this->warn($exception->getMessage());

                    return self::FAILURE;
                }

                // Left out of a search that worked: hidden from this account for now.
                if ($issue === null) {
                    continue;
                }

                foreach ($links as $link) {
                    $syncIssue->handle($link, $issue);
                }
            }
        }

        return self::SUCCESS;
    }
}
