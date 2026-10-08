<?php

namespace App\Domain\Workflows\Models;

use App\Domain\Accounts\Enums\Permission;
use App\Domain\Entitlements\Enums\Feature;
use App\Domain\Entitlements\Support\PlanLimits;
use App\Domain\Workflows\Enums\WorkflowTrigger;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Factories\WorkflowFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An automation drawn as a graph: one trigger node followed by logic and action nodes.
 *
 * `graph` is stored in the editor's (React Flow) shape:
 * `{nodes: list<{id, type, position: {x, y}, data: array}>, edges: list<{id, source, sourceHandle, target}>}`.
 *
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property bool $is_active
 * @property WorkflowTrigger $trigger
 * @property array{nodes: list<array{id: string, type: string, position?: array{x: float|int, y: float|int}, data?: array<string, mixed>, parentId?: string}>, edges: list<array{id?: string, source: string, sourceHandle?: string|null, target: string}>} $graph
 * @property int $max_runs_per_ticket 0 means no limit.
 * @property bool $apply_to_existing Time-based workflows: also act on tickets created before the workflow was activated.
 * @property string|null $disabled_reason Why the workflow was switched off automatically.
 * @property CarbonImmutable|null $activated_at
 * @property CarbonImmutable|null $last_run_at
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read User|null $creator
 */
#[Fillable(['name', 'description', 'is_active', 'trigger', 'graph', 'max_runs_per_ticket', 'apply_to_existing', 'disabled_reason', 'activated_at', 'last_run_at', 'created_by', 'updated_by'])]
#[UseFactory(WorkflowFactory::class)]
class Workflow extends Model
{
    /** @use HasFactory<WorkflowFactory> */
    use HasFactory;

    /**
     * Runs per ticket when the limit is "unlimited"; the last line of defence against loops.
     */
    public const int HARD_RUN_LIMIT = 100;

    protected static function booted(): void
    {
        static::saving(function (Workflow $workflow): void {
            if ($workflow->is_active && ($workflow->isDirty('is_active') || $workflow->activated_at === null)) {
                $workflow->activated_at = now();
                $workflow->disabled_reason = null;
            }
        });
    }

    /**
     * @return HasMany<WorkflowRun, $this>
     */
    public function runs(): HasMany
    {
        return $this->hasMany(WorkflowRun::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * Active manual workflows the user may run on tickets they can edit.
     *
     * @return Collection<int, self>
     */
    public static function runnableBy(User $user): Collection
    {
        if (! PlanLimits::allows(Feature::Workflows)) {
            return new Collection;
        }

        return self::query()
            ->active()
            ->where('trigger', WorkflowTrigger::Manual)
            ->orderBy('name')
            ->get()
            ->filter(fn (self $workflow): bool => $workflow->isRunnableBy($user))
            ->values();
    }

    public function isRunnableBy(User $user): bool
    {
        return $this->is_active
            && $this->trigger === WorkflowTrigger::Manual
            && $user->hasPermission(Permission::RunWorkflows)
            && (($this->triggerSettings()['run_by'] ?? 'agents') !== 'admins' || $user->isAdmin());
    }

    /**
     * Settings of the trigger node (which fields changed, idle hours, who may run it...).
     *
     * @return array<string, mixed>
     */
    public function triggerSettings(): array
    {
        foreach ($this->graph['nodes'] as $node) {
            if ($node['type'] === 'trigger') {
                return $node['data'] ?? [];
            }
        }

        return [];
    }

    /**
     * The most runs a single ticket may have.
     */
    public function runLimit(): int
    {
        return $this->max_runs_per_ticket > 0 ? min($this->max_runs_per_ticket, self::HARD_RUN_LIMIT) : self::HARD_RUN_LIMIT;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'trigger' => WorkflowTrigger::class,
            'graph' => 'array',
            'max_runs_per_ticket' => 'integer',
            'apply_to_existing' => 'boolean',
            'activated_at' => 'datetime',
            'last_run_at' => 'datetime',
        ];
    }
}
