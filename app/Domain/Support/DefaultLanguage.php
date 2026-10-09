<?php

namespace App\Domain\Support;

use App\Domain\Support\Models\Setting;

/**
 * The language of the interface and emails for everyone who hasn't picked one in their
 * profile. Admins choose it in the admin center; until then, APP_LOCALE applies.
 *
 * Not read from app.locale, which App::setLocale() changes to the language of the request.
 */
final class DefaultLanguage
{
    public const string SETTING = 'locale';

    public static function current(): string
    {
        $locale = Setting::get(self::SETTING);

        return is_string($locale) && self::isAvailable($locale) ? $locale : (string) config('kitedesk.default_locale');
    }

    public static function save(string $locale): void
    {
        Setting::put(self::SETTING, $locale);
    }

    public static function isAvailable(string $locale): bool
    {
        return array_key_exists($locale, config('kitedesk.locales', []));
    }

    /**
     * The name of the current default, e.g. "English".
     */
    public static function label(): string
    {
        return (string) config('kitedesk.locales.'.self::current(), self::current());
    }
}
