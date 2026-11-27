<?php

namespace App\Domain\Jira\Jobs;

use App\Domain\Jira\Actions\ImportComment;
use App\Domain\Jira\Enums\CommentDirection;
use App\Domain\Jira\Exceptions\JiraNotConnected;
use App\Domain\Jira\Exceptions\JiraRateLimited;
use App\Domain\Jira\Models\JiraCommentLink;
use App\Domain\Jira\Models\JiraIssueLink;
use App\Domain\Jira\Support\Adf;
use App\Domain\Jira\Support\JiraClient;
use App\Domain\Jira\Support\JiraModule;
use App\Domain\Jira\Support\TicketRemoteLink;
use App\Domain\Tickets\Models\TicketMessage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Posts an internal note to a linked issue as a comment, once.
 */
class PostCommentToJira implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public function __construct(public JiraIssueLink $link, public TicketMessage $message) {}

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [10, 60, 300, 1800];
    }

    public function handle(): void
    {
        $link = $this->link;

        if (! JiraModule::isEnabled() || $link->deleted_at_jira !== null || $this->alreadyPosted()) {
            return;
        }

        $ticket = $link->ticket;
        $author = $this->message->author->name ?? __('System');

        $body = Adf::document([
            Adf::paragraph(ImportComment::MARKER.' '.$ticket->reference().' · '.$author.':', TicketRemoteLink::url($ticket), strong: true),
            ...Adf::fromHtml($this->message->body)['content'],
        ]);

        try {
            $commentId = JiraClient::make()->addComment($link->issue_key, $body);
        } catch (JiraRateLimited $exception) {
            $this->release($exception->retryAfter);

            return;
        } catch (JiraNotConnected) {
            // Admins were told; posting again won't help until the credentials are fixed.
            $this->fail();

            return;
        }

        JiraCommentLink::query()->create([
            'jira_issue_link_id' => $link->id,
            'ticket_message_id' => $this->message->id,
            'jira_comment_id' => $commentId,
            'direction' => CommentDirection::Outbound,
        ]);

        $metadata = $this->message->metadata ?? [];
        $metadata['jira_sent'] = array_values(array_unique([...($metadata['jira_sent'] ?? []), $link->issue_key]));
        $this->message->update(['metadata' => $metadata]);
    }

    private function alreadyPosted(): bool
    {
        return JiraCommentLink::query()
            ->where('jira_issue_link_id', $this->link->id)
            ->where('ticket_message_id', $this->message->id)
            ->where('direction', CommentDirection::Outbound)
            ->exists();
    }
}
