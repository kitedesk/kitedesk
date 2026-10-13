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

    /**
     * Save a setting; what changed goes to the audit log.
     */
    public static function put(string $key, mixed $value): void
    {
        $previous = self::query()->find($key)?->value;

        self::query()->updateOrCreate(['key' => $key], ['value' => $value]);
        Cache::forget(self::cacheKey($key));

        self::audit($key, $previous, $value);
    }

    /**
     * Records the fields that changed, from what to what. Secrets (API keys, tokens…) are
     * stored encrypted, so only whether one is set is recorded, never its value.
     */
    private static function audit(string $key, mixed $previous, mixed $value): void
    {
        $previous = is_array($previous) ? $previous : ['value' => $previous];
        $value = is_array($value) ? $value : ['value' => $value];
        $changes = ['old' => [], 'attributes' => []];

        foreach (array_unique([...array_keys($previous), ...array_keys($value)]) as $field) {
            $before = $previous[$field] ?? null;
            $after = $value[$field] ?? null;

            if (preg_match('/key|secret|password|token/i', (string) $field) === 1) {
                $before = $before === null ? null : '********';
                $after = $after === null ? null : '********';
            }

            // Loosely: stored values come back from JSON, so 5 and "5" are the same setting.
            if ($before != $after) {
                $changes['old'][$field] = $before;
                $changes['attributes'][$field] = $after;
            }
        }

        if ($changes['attributes'] !== []) {
            activity()->useLog('settings')->withChanges($changes)->withProperties(['setting' => $key])->event('settings_updated')->log('settings_updated');
        }
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
