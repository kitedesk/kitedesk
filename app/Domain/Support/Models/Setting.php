<?php

namespace App\Domain\Support\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Installation-wide settings that admins change in the admin center.
 *
 * @property string $key
 * @property mixed $value
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['key', 'value'])]
class Setting extends Model
{
    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * Read a setting; values are cached until they change.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $stored = Cache::rememberForever(self::cacheKey($key), fn (): array => ['value' => self::query()->find($key)?->value]);

        return $stored['value'] ?? $default;
    }

    public static function put(string $key, mixed $value): void
    {
        self::query()->updateOrCreate(['key' => $key], ['value' => $value]);
        Cache::forget(self::cacheKey($key));
    }

    private static function cacheKey(string $key): string
    {
        return "settings:{$key}";
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'value' => 'json',
        ];
    }
}
