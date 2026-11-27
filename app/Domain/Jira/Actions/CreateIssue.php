<?php

namespace App\Domain\Jira\Actions;

use App\Domain\Jira\Exceptions\JiraException;
use App\Domain\Jira\Models\JiraIssueLink;
use App\Domain\Jira\Support\Adf;
use App\Domain\Jira\Support\JiraClient;
use App\Domain\Jira\Support\TicketRemoteLink;
use App\Domain\Tickets\Models\Ticket;
use App\Domain\Tickets\Models\TicketMessage;
use App\Models\User;

/**
 * Creates a Jira issue from a ticket and links them. The description is the customer's first
 * message and a link back to the ticket. Issue types that require more fields than the
 * summary and description are refused, with the fields named, so the agent can create the
 * issue in Jira and link it instead.
 */
class CreateIssue
{
    public function __construct(private LinkIssue $linkIssue) {}

    /**
     * @throws JiraException
     */
    public function handle(Ticket $ticket, string $project, string $issueType, string $summary, ?User $user): JiraIssueLink
    {
        $client = JiraClient::make();
        $missing = $client->missingRequiredFields($project, $issueType);

        if ($missing !== []) {
            throw new JiraException(__('This issue type also needs: :fields. Create the issue in Jira and link it here.', ['fields' => implode(', ', $missing)]), 422);
        }

        $issue = $client->createIssue($project, $issueType, $summary, $this->description($ticket));

        return $this->linkIssue->handle($ticket, $issue['key'], $user, $issue);
    }

    /**
     * @return array{type: 'doc', version: 1, content: list<array<string, mixed>>}
     */
    private function description(Ticket $ticket): array
    {
        /** @var TicketMessage|null $first */
        $first = $ticket->messages()->public()->oldest()->oldest('id')->first();

        return Adf::document([
            ...($first !== null ? Adf::fromHtml($first->body)['content'] : []),
            Adf::paragraph(__('Support ticket :reference', ['reference' => $ticket->reference()]), TicketRemoteLink::url($ticket)),
        ]);
    }
}
