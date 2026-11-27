<?php

namespace App\Domain\Jira\Jobs;

use App\Domain\Jira\Exceptions\JiraException;
use App\Domain\Jira\Exceptions\JiraRateLimited;
use App\Domain\Jira\Models\JiraIssueLink;
use App\Domain\Jira\Support\JiraClient;
use App\Domain\Jira\Support\JiraModule;
use App\Domain\Jira\Support\TicketRemoteLink;
use App\Domain\Tickets\Models\Ticket;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\DeleteWhenMissingModels;

/**
 * Updates the ticket's entry in its issues' links: its title, and whether it is resolved.
 *
 * Skipped when its records are gone by the time it runs (e.g. the issue was unlinked).
 */
#[DeleteWhenMissingModels]
class SyncRemoteLinks implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public Ticket $ticket) {}

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [30, 300];
    }

    public function handle(): void
    {
        if (! JiraModule::isEnabled()) {
            return;
        }

        $client = JiraClient::make();

        $links = JiraIssueLink::query()->where('ticket_id', $this->ticket->id)->whereNull('deleted_at_jira')->get();

        foreach ($links as $link) {
            try {
                TicketRemoteLink::push($client, $link->issue_key, $this->ticket);
            } catch (JiraRateLimited $exception) {
                $this->release($exception->retryAfter);

                return;
            } catch (JiraException) {
                // The link itself still works; the issue may be gone or locked down.
            }
        }
    }
}
