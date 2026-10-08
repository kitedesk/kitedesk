<?php

namespace App\Domain\Tickets\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $name
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Collection<int, Ticket> $tickets
 */
#[Fillable(['name'])]
class Tag extends Model
{
    /**
     * Normalize a list of tag names and return their ids, creating missing tags.
     *
     * @param  list<string>  $names
     * @return list<int>
     */
    public static function idsFor(array $names): array
    {
        return array_values(collect($names)
            ->map(fn (string $name): string => Str::of($name)->trim()->lower()->replaceMatches('/\s+/', '_')->toString())
            ->filter()
            ->unique()
            ->map(fn (string $name): int => static::query()->firstOrCreate(['name' => $name])->id)
            ->all());
    }

    /**
     * @return BelongsToMany<Ticket, $this>
     */
    public function tickets(): BelongsToMany
    {
        return $this->belongsToMany(Ticket::class);
    }
}
