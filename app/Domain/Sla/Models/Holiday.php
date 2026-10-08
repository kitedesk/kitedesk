<?php

namespace App\Domain\Sla\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $business_schedule_id
 * @property string $name
 * @property CarbonImmutable $date
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read BusinessSchedule $businessSchedule
 */
#[Fillable(['business_schedule_id', 'name', 'date'])]
class Holiday extends Model
{
    protected static function booted(): void
    {
        static::saved(fn () => SlaPolicy::forgetEvaluationCache());
        static::deleted(fn () => SlaPolicy::forgetEvaluationCache());
    }

    /**
     * @return BelongsTo<BusinessSchedule, $this>
     */
    public function businessSchedule(): BelongsTo
    {
        return $this->belongsTo(BusinessSchedule::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
        ];
    }
}
