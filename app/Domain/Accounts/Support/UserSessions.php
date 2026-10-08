<?php

namespace App\Domain\Accounts\Support;

use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The browsers a person is signed in on, read from the database session store. With any
 * other session driver there is nothing to list or end, so both methods do nothing.
 */
class UserSessions
{
    /**
     * @return list<array{id: string, browser: string|null, platform: string|null, is_mobile: bool, ip_address: string|null, last_active_at: string, is_current: bool}>
     */
    public static function for(User $user, ?string $currentId = null): array
    {
        if (! self::available()) {
            return [];
        }

        return array_values(self::query()
            ->where('user_id', $user->getAuthIdentifier())
            ->orderByDesc('last_activity')
            ->get(['id', 'ip_address', 'user_agent', 'last_activity'])
            ->map(function (object $session) use ($currentId): array {
                $agent = (string) ($session->user_agent ?? '');

                return [
                    'id' => hash('sha256', (string) $session->id),
                    'browser' => self::browser($agent),
                    'platform' => self::platform($agent),
                    'is_mobile' => (bool) preg_match('/Mobile|Android|iPhone|iPad/i', $agent),
                    'ip_address' => is_string($session->ip_address) ? $session->ip_address : null,
                    'last_active_at' => Carbon::createFromTimestamp((int) $session->last_activity)->toIso8601String(),
                    'is_current' => $currentId !== null && hash_equals((string) $session->id, $currentId),
                ];
            })
            ->all());
    }

    /**
     * End the user's sessions, except the given one.
     */
    public static function forget(User $user, ?string $exceptId = null): int
    {
        if (! self::available()) {
            return 0;
        }

        return self::query()
            ->where('user_id', $user->getAuthIdentifier())
            ->when($exceptId !== null, fn (Builder $query) => $query->where('id', '!=', $exceptId))
            ->delete();
    }

    private static function available(): bool
    {
        return config('session.driver') === 'database';
    }

    private static function query(): Builder
    {
        return DB::connection(config('session.connection'))->table((string) config('session.table', 'sessions'));
    }

    private static function browser(string $agent): ?string
    {
        return match (true) {
            Str::contains($agent, 'Edg/') => 'Edge',
            Str::contains($agent, ['OPR/', 'Opera']) => 'Opera',
            Str::contains($agent, ['Firefox/', 'FxiOS/']) => 'Firefox',
            Str::contains($agent, ['Chrome/', 'CriOS/']) => 'Chrome',
            Str::contains($agent, 'Safari/') => 'Safari',
            default => null,
        };
    }

    private static function platform(string $agent): ?string
    {
        return match (true) {
            Str::contains($agent, ['iPhone', 'iPad']) => 'iOS',
            Str::contains($agent, 'Android') => 'Android',
            Str::contains($agent, 'Windows') => 'Windows',
            Str::contains($agent, ['Macintosh', 'Mac OS X']) => 'macOS',
            Str::contains($agent, 'CrOS') => 'ChromeOS',
            Str::contains($agent, 'Linux') => 'Linux',
            default => null,
        };
    }
}
