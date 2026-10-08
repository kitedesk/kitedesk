<?php

namespace App\Domain\Support;

use App\Domain\Accounts\Enums\UserType;
use App\Models\User;

/**
 * Whether this installation still has to be set up, and the code that lets someone do it.
 */
final class Installation
{
    /**
     * A new installation has no staff yet, so the setup screen creates the first administrator
     * (unless `kitedesk.setup_screen` is off, for installations that create it themselves).
     */
    public static function needsSetup(): bool
    {
        return config('kitedesk.setup_screen') && ! self::hasStaff();
    }

    public static function hasStaff(): bool
    {
        return User::query()->where('type', UserType::Staff)->exists();
    }

    /**
     * Proves the person on the setup screen runs the server: it's only shown by
     * `php artisan kitedesk:setup` (in the container logs on Docker). Derived from APP_KEY, so
     * every process agrees on it without storing anything.
     */
    public static function setupCode(): string
    {
        return substr(hash_hmac('sha256', 'kitedesk-setup', (string) config('app.key')), 0, 24);
    }

    public static function setupUrl(): string
    {
        return route('setup.show', ['code' => self::setupCode()]);
    }
}
