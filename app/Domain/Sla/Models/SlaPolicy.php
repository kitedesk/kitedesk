<?php

namespace App\Domain\Sla\Models;

use App\Domain\Tickets\Enums\TicketPriority;
use App\Domain\Tickets\Models\Ticket;
use Carbon\CarbonImmutable;
use Database\Factories\SlaPolicyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;

/**
 * Service level targets, in minutes, per ticket priority.
 *
 * `conditions` restricts which tickets the policy applies to; an empty list means "any":
 * ["priorities" => list<string>, "group_ids" => list<int>, "organization_ids" => list<int>].
 *
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property int|null $business_schedule_id
 * @property array{priorities?: list<string>, group_ids?: list<int>, organization_ids?: list<int>}|null $conditions
 * @property array<string, array{first_response?: int|null, next_reply?: int|null, resolution?: int|null}> $targets
 * @property int $position
 * @property bool $is_active
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read BusinessSchedule|null $businessSchedule
 */
#[Fillable(['name', 'description', 'business_schedule_id', 'conditions', 'targets', 'position', 'is_active'])]
#[UseFactory(SlaPolicyFactory::class)]
class SlaPolicy extends Model
{
    /** @use HasFactory<SlaPolicyFactory> */
    use HasFactory;

    private const string EVALUATION_CACHE_KEY = 'sla:policies';

    protected static function booted(): void
    {
        static::saved(fn () => self::forgetEvaluationCache());
        static::deleted(fn () => self::forgetEvaluationCache());
    }

    /**
     * Active policies in evaluation order with their schedules and holidays, cached until
     * a policy, schedule or holiday changes (every ticket create/update looks them up).
     *
     * @return Collection<int, self>
     */
    public static function forEvaluation(): Collection
    {
        // Plain attribute arrays are cached (the cache refuses to restore objects) and turned back into models.
        $rows = Cache::rememberForever(self::EVALUATION_CACHE_KEY, fn (): array => self::query()
            ->inEvaluationOrder()
            ->with('businessSchedule.holidays')
            ->get()
            ->map(fn (self $policy): array => [
                'policy' => $policy->getAttributes(),
                'schedule' => $policy->businessSchedule?->getAttributes(),
                'holidays' => $policy->businessSchedule?->holidays->map(fn (Holiday $holiday): array => $holiday->getAttributes())->all() ?? [],
            ])
            ->all());

        return (new self)->newCollection(array_map(function (array $row): self {
            $policy = (new self)->newFromBuilder($row['policy']);
            $schedule = $row['schedule'] !== null ? (new BusinessSchedule)->newFromBuilder($row['schedule']) : null;

            $schedule?->setRelation('holidays', Holiday::hydrate($row['holidays']));

            return $policy->setRelation('businessSchedule', $schedule);
        }, $rows));
    }

    public static function forgetEvaluationCache(): void
    {
        Cache::forget(self::EVALUATION_CACHE_KEY);
    }

    /**
     * @return BelongsTo<BusinessSchedule, $this>
     */
    public function businessSchedule(): BelongsTo
    {
        return $this->belongsTo(BusinessSchedule::class);
    }

    /**
     * Active policies in evaluation order (first match wins).
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function inEvaluationOrder(Builder $query): void
    {
        $query->where('is_active', true)->orderBy('position')->orderBy('id');
    }

    /**
     * Whether the policy's conditions match the given ticket.
     */
    public function matches(Ticket $ticket): bool
    {
        $conditions = $this->conditions ?? [];

        return $this->conditionAllows($conditions['priorities'] ?? [], $ticket->priority->value)
            && $this->conditionAllows($conditions['group_ids'] ?? [], $ticket->group_id)
            && $this->conditionAllows($conditions['organization_ids'] ?? [], $ticket->organization_id);
    }

    /**
     * Target in minutes for the given metric and priority, or null if not tracked.
     *
     * @param  'first_response'|'next_reply'|'resolution'  $metric
     */
    public function targetMinutes(string $metric, TicketPriority $priority): ?int
    {
        $minutes = $this->targets[$priority->value][$metric] ?? null;

        return $minutes === null ? null : (int) $minutes;
    }

    /**
     * @param  list<int|string>  $allowed
     */
    private function conditionAllows(array $allowed, int|string|null $value): bool
    {
        return $allowed === [] || in_array($value, $allowed, false);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'conditions' => 'array',
            'targets' => 'array',
            'is_active' => 'boolean',
        ];
    }
}
