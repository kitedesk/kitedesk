<?php

namespace App\Domain\Support\Sidebar;

use App\Domain\Support\Models\Setting;
use App\Models\User;

/**
 * How the agent sidebar is laid out: `menus` groups ticket views and each module's pages into
 * collapsible menus with submenus; `legacy` is the flat list of views, saved views and statuses
 * with the modules' pages below. Admins pick the installation default, agents may override it
 * for themselves (stored in `users.preferences`).
 */
final readonly class SidebarSettings
{
    public const string SETTING = 'sidebar';

    public const string MENUS = 'menus';

    public const string LEGACY = 'legacy';

    public const array MODES = [self::MENUS, self::LEGACY];

    public static function default(): string
    {
        $setting = Setting::get(self::SETTING);
        $mode = is_array($setting) ? ($setting['mode'] ?? null) : null;

        return in_array($mode, self::MODES, true) ? $mode : self::MENUS;
    }

    public static function saveDefault(string $mode): void
    {
        Setting::put(self::SETTING, ['mode' => $mode]);
    }

    /**
     * What the agent chose for themselves; null follows the installation default.
     */
    public static function preference(User $user): ?string
    {
        $mode = ($user->preferences ?? [])['sidebar'] ?? null;

        return in_array($mode, self::MODES, true) ? $mode : null;
    }

    public static function savePreference(User $user, ?string $mode): void
    {
        $preferences = $user->preferences ?? [];

        if ($mode === null) {
            unset($preferences['sidebar']);
        } else {
            $preferences['sidebar'] = $mode;
        }

        $user->forceFill(['preferences' => $preferences])->save();
    }

    public static function for(User $user): string
    {
        return self::preference($user) ?? self::default();
    }

    /**
     * What the pages receive.
     *
     * @return array{mode: string, default: string, preference: string|null}
     */
    public static function shared(User $user): array
    {
        return ['mode' => self::for($user), 'default' => self::default(), 'preference' => self::preference($user)];
    }
}
