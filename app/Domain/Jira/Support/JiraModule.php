<?php

namespace App\Domain\Jira\Support;

use App\Domain\Support\Extensions\ModuleRegistry;

final class JiraModule
{
    public const string ID = 'jira';

    /**
     * Switched on by an admin. Listeners, jobs and scheduled commands check this themselves.
     */
    public static function isEnabled(): bool
    {
        return app(ModuleRegistry::class)->isEnabled(self::ID);
    }
}
