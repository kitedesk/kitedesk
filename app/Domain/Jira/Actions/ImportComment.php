<?php

namespace App\Domain\Jira\Actions;

use App\Domain\Jira\Enums\CommentDirection;
use App\Domain\Jira\Exceptions\JiraException;
use App\Domain\Jira\Models\JiraCommentLink;
use App\Domain\Jira\Models\JiraIssueLink;
use App\Domain\Jira\Support\Adf;
use App\Domain\Jira\Support\JiraClient;
use App\Domain\Jira\Support\WikiMarkup;
use App\Domain\Support\RichText;
use App\Domain\Tickets\Actions\AddMessage;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Copies a Jira comment to the ticket as an internal note, once. Comments KiteDesk posted
 * itself are skipped, so notes don't bounce back and forth: those written by the Jira
 * account KiteDesk signs in as, and (in case that account can't be looked up) those that
 * start with KiteDesk's marker.
 */
class ImportComment
{
    /** Every comment KiteDesk posts starts with this, followed by the ticket reference. */
    public const string MARKER = 'From KiteDesk ticket';

    public function __construct(private AddMessage $addMessage) {}

    /**
     * @param  array<string, mixed>  $comment  a comment as Jira sends it (body in ADF or plain text)
     */
    public function handle(JiraIssueLink $link, array $comment): void
    {
        $body = $comment['body'] ?? '';
        $html = is_array($body) ? Adf::toHtml($body) : WikiMarkup::toHtml((string) $body);

        if ($this->isOwn($comment, $html)) {
            return;
        }

        $author = (string) ($comment['author']['displayName'] ?? __('Someone'));

        try {
            // Together, so a note that fails to save leaves the comment to import on the next delivery.
            DB::transaction(function () use ($link, $comment, $author, $html): void {
                $record = JiraCommentLink::query()->create([
                    'jira_issue_link_id' => $link->id,
                    'jira_comment_id' => (string) $comment['id'],
                    'direction' => CommentDirection::Inbound,
                ]);

                $message = $this->addMessage->handle(
                    $link->ticket,
                    null,
                    '<p>'.e(__(':author commented on :key in Jira:', ['author' => $author, 'key' => $link->issue_key])).'</p>'.RichText::sanitize($html),
                    isInternal: true,
                    metadata: ['jira' => ['issue_key' => $link->issue_key, 'kind' => 'comment', 'author' => $author]],
                );

                $record->update(['ticket_message_id' => $message->id]);
            });
        } catch (UniqueConstraintViolationException) {
            // Already imported (Jira delivers webhooks at least once).
        }
    }

    /**
     * @param  array<string, mixed>  $comment
     */
    private function isOwn(array $comment, string $html): bool
    {
        if (str_starts_with(trim(html_entity_decode(strip_tags($html))), self::MARKER)) {
            return true;
        }

        $author = $comment['author']['accountId'] ?? null;

        if (! is_string($author) || $author === '') {
            return false;
        }

        try {
            return $author === JiraClient::make()->accountId();
        } catch (JiraException) {
            return false;
        }
    }
}
