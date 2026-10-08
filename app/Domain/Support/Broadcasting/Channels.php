<?php

namespace App\Domain\Support\Broadcasting;

/**
 * Broadcast channel names carry a scope segment ("kitedesk.staff.tickets") so several
 * installations, such as the hosted edition's workspaces, can share one Reverb app without
 * hearing each other. The scope is read on every call, so it can change per request.
 */
class Channels
{
    public static function scope(): string
    {
        return (string) config('broadcasting.channel_scope', 'kitedesk');
    }

    /**
     * The full name to broadcast on, e.g. "staff.tickets" → "kitedesk.staff.tickets".
     */
    public static function name(string $channel): string
    {
        return self::scope().'.'.$channel;
    }

    /**
     * The pattern to authorize in routes/channels.php; the callback receives `$scope` first
     * and must check it with {@see self::matches()}.
     */
    public static function pattern(string $channel): string
    {
        return '{scope}.'.$channel;
    }

    public static function matches(string $scope): bool
    {
        return hash_equals(self::scope(), $scope);
    }
}
