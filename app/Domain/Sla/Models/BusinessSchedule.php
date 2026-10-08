<?php

namespace App\Domain\Sla\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Weekly working hours used to measure SLA targets in business time.
 *
 * `hours` is keyed by ISO day of week (1 = Monday ... 7 = Sunday); each day holds
 * a list of ["start" => "HH:MM", "end" => "HH:MM"] intervals in the schedule's timezone.
 *
 * @property int $id
 * @property string $name
 * @property string $timezone
 * @property array<int|string, list<array{start: string, end: string}>> $hours
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Collection<int, Holiday> $holidays
 */
#[Fillable(['name', 'timezone', 'hours'])]
class BusinessSchedule extends Model
{
    protected static function booted(): void
    {
        static::saved(fn () => SlaPolicy::forgetEvaluationCache());
        static::deleted(fn () => SlaPolicy::forgetEvaluationCache());
    }

    /**
     * @return HasMany<Holiday, $this>
     */
    public function holidays(): HasMany
    {
        return $this->hasMany(Holiday::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'hours' => 'array',
        ];
    }
}
