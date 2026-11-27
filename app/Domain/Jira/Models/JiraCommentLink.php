<?php

namespace App\Domain\Jira\Models;

use App\Domain\Jira\Enums\CommentDirection;
use App\Domain\Tickets\Models\TicketMessage;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A comment copied between a ticket and a linked issue, in either direction.
 *
 * @property int $id
 * @property int $jira_issue_link_id
 * @property int|null $ticket_message_id
 * @property string $jira_comment_id
 * @property CommentDirection $direction
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['jira_issue_link_id', 'ticket_message_id', 'jira_comment_id', 'direction'])]
class JiraCommentLink extends Model
{
    /**
     * @return BelongsTo<JiraIssueLink, $this>
     */
    public function issueLink(): BelongsTo
    {
        return $this->belongsTo(JiraIssueLink::class, 'jira_issue_link_id');
    }

    /**
     * @return BelongsTo<TicketMessage, $this>
     */
    public function message(): BelongsTo
    {
        return $this->belongsTo(TicketMessage::class, 'ticket_message_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['direction' => CommentDirection::class];
    }
}
