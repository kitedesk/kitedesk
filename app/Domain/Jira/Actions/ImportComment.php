<?php

namespace App\Domain\Jira\Actions;

use App\Domain\Jira\Enums\CommentDirection;
use App\Domain\Jira\Models\JiraCommentLink;
use App\Domain\Jira\Models\JiraIssueLink;
use App\Domain\Jira\Support\Adf;
use App\Domain\Support\RichText;
use App\Domain\Tickets\Actions\AddMessage;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Copies a Jira comment to the ticket as an internal note, once. Comments KiteDesk posted
 * itself are recognised and skipped, so notes don't bounce back and forth.
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
        $html = is_array($body) ? Adf::toHtml($body) : self::plainTextToHtml((string) $body);

        if (str_starts_with(trim(strip_tags($html)), self::MARKER)) {
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

    private static function plainTextToHtml(string $text): string
    {
        $paragraphs = preg_split('/\R{2,}/', trim($text)) ?: [];

        return implode('', array_map(fn (string $paragraph): string => '<p>'.nl2br(e($paragraph), false).'</p>', $paragraphs));
    }
}
