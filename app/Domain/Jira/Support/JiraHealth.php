<?php

namespace App\Domain\Jira\Support;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;

/**
 * The last time Jira refused KiteDesk's credentials, shown on the admin page and the ticket
 * panel until the settings are saved again or a call succeeds. Kept in the cache rather than
 * the settings, so failures don't fill the settings audit log.
 */
final class JiraHealth
{
    private const string CACHE_KEY = 'jira:health';

    /**
     * @return array{message: string, at: string}|null
     */
    public static function error(): ?array
    {
        $error = Cache::get(self::CACHE_KEY);

        return is_array($error) && is_string($error['message'] ?? null) && is_string($error['at'] ?? null)
            ? ['message' => $error['message'], 'at' => $error['at']]
            : null;
    }

    /**
     * Records the failure. Returns true the first time, so one notification goes out per outage.
     */
    public static function fail(string $message): bool
    {
        $first = self::error() === null;

        Cache::forever(self::CACHE_KEY, ['message' => $message, 'at' => CarbonImmutable::now()->toIso8601String()]);

        return $first;
    }

    public static function clear(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
