<?php

namespace App\Domain\Jira\Console;

use App\Domain\Jira\Actions\SyncIssue;
use App\Domain\Jira\Exceptions\JiraException;
use App\Domain\Jira\Models\JiraIssueLink;
use App\Domain\Jira\Support\JiraClient;
use App\Domain\Jira\Support\JiraModule;
use App\Domain\Jira\Support\JiraSettings;
use Illuminate\Console\Command;

/**
 * Catches up on linked issues whose webhooks never arrived: every issue not refreshed in the
 * last 15 minutes is read again, 100 at a time.
 */
class RefreshJiraIssuesCommand extends Command
{
    protected $signature = 'jira:refresh';

    protected $description = 'Refresh linked Jira issues that webhooks may have missed';

    public function handle(SyncIssue $syncIssue): int
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
                $this->warn($exception->getMessage());

                return self::FAILURE;
            }

            foreach ($batch as $issueId => $links) {
                $issue = $issues->get((string) $issueId);

                // Missing: deleted, moved out of reach, or hidden from this account. Webhooks report deletions.
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
