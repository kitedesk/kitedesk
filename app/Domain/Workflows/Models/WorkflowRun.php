<?php

namespace App\Domain\Workflows\Models;

use App\Domain\Tickets\Models\Ticket;
use App\Domain\Workflows\Enums\RunStatus;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One execution of a workflow for one ticket.
 *
 * The graph is copied when the run starts, so editing the workflow never breaks a run that
 * is waiting. `context` holds the trigger payload (`changes`, `message_id`), variables
 * (`vars`) and, while waiting, what the run waits for (`waiting_for`, `resume_handle`).
 *
 * @property int $id
 * @property int $workflow_id
 * @property int $ticket_id
 * @property RunStatus $status
 * @property string $trigger_event
 * @property array<string, mixed>|null $context
 * @property array{nodes: list<array<string, mixed>>, edges: list<array<string, mixed>>} $graph
 * @property string|null $current_node_id
 * @property CarbonImmutable|null $resume_at
 * @property int $depth How many workflow runs led to this one (loop protection).
 * @property int|null $started_by
 * @property string|null $error
 * @property CarbonImmutable|null $started_at
 * @property CarbonImmutable|null $finished_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Workflow $workflow
 * @property-read Ticket $ticket
 * @property-read User|null $starter
 */
#[Fillable(['workflow_id', 'ticket_id', 'status', 'trigger_event', 'context', 'graph', 'current_node_id', 'resume_at', 'depth', 'started_by', 'error', 'started_at', 'finished_at'])]
class WorkflowRun extends Model
{
    use Prunable;

    /**
     * Finished runs are kept this long (`model:prune`).
     */
    public const int RETENTION_DAYS = 30;

    /**
     * @return Builder<static>
     */
    public function prunable(): Builder
    {
        return static::query()
            ->whereNotIn('status', [RunStatus::Running, RunStatus::Waiting])
            ->where('created_at', '<', now()->subDays(self::RETENTION_DAYS));
    }

    /**
     * @return BelongsTo<Workflow, $this>
     */
    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class);
    }

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
    public function starter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'started_by');
    }

    /**
     * @return HasMany<WorkflowRunStep, $this>
     */
    public function steps(): HasMany
    {
        return $this->hasMany(WorkflowRunStep::class)->orderBy('id');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => RunStatus::class,
            'context' => 'array',
            'graph' => 'array',
            'resume_at' => 'datetime',
            'depth' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }
}
