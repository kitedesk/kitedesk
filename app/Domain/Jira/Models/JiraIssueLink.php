<?php

namespace App\Domain\Jira\Models;

use App\Domain\Jira\Enums\StatusCategory;
use App\Domain\Tickets\Models\Ticket;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Factories\JiraIssueLinkFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A ticket linked to a Jira issue, with what was last seen of the issue.
 *
 * @property int $id
 * @property int $ticket_id
 * @property string $issue_id
 * @property string $issue_key
 * @property string $summary
 * @property string|null $issue_type
 * @property string|null $status_id
 * @property string|null $status_name
 * @property StatusCategory|null $status_category
 * @property string|null $resolution
 * @property string|null $assignee_name
 * @property string $url
 * @property CarbonImmutable|null $deleted_at_jira
 * @property int|null $linked_by
 * @property CarbonImmutable|null $last_synced_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Ticket $ticket
 */
#[Fillable(['ticket_id', 'issue_id', 'issue_key', 'summary', 'issue_type', 'status_id', 'status_name', 'status_category', 'resolution', 'assignee_name', 'url', 'deleted_at_jira', 'linked_by', 'last_synced_at'])]
#[UseFactory(JiraIssueLinkFactory::class)]
class JiraIssueLink extends Model
{
    /** @use HasFactory<JiraIssueLinkFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Ticket, $this>
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function linker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'linked_by');
    }

    /**
     * @return HasMany<JiraCommentLink, $this>
     */
    public function comments(): HasMany
    {
        return $this->hasMany(JiraCommentLink::class);
    }

    public function isDone(): bool
    {
        return $this->status_category === StatusCategory::Done;
    }

    /**
     * The issue as the ticket panel shows it.
     *
     * @return array{id: int, key: string, summary: string, type: string|null, status: string|null, status_category: string|null, resolution: string|null, assignee: string|null, url: string, deleted: bool}
     */
    public function toPanelArray(): array
    {
        return [
            'id' => $this->id,
            'key' => $this->issue_key,
            'summary' => $this->summary,
            'type' => $this->issue_type,
            'status' => $this->status_name,
            'status_category' => $this->status_category?->value,
            'resolution' => $this->resolution,
            'assignee' => $this->assignee_name,
            'url' => $this->url,
            'deleted' => $this->deleted_at_jira !== null,
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status_category' => StatusCategory::class,
            'deleted_at_jira' => 'immutable_datetime',
            'last_synced_at' => 'immutable_datetime',
        ];
    }
}
