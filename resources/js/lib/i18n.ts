/**
 * Tiny translation runtime. Keys are the English text (Laravel's JSON translation
 * convention), so a missing translation falls back to readable English.
 *
 * Components call `useTranslation()`, which keeps this module in sync with the
 * shared `locale` and `translations` props; plain helpers can use `t` directly.
 */

export type Replacements = Record<string, string | number>;

let currentLocale = 'en';
let currentTimeZone: string | undefined;
let strings: Record<string, string> = {};

export function setTranslations(
    locale: string,
    translations: Record<string, string> | undefined,
): void {
    currentLocale = locale;
    strings = translations ?? {};
}

export function getLocale(): string {
    return currentLocale;
}

/**
 * The timezone picked in the profile; dates use the browser's when there is none (or the
 * browser doesn't know it).
 */
export function setTimeZone(timeZone: string | null | undefined): void {
    currentTimeZone = undefined;

    if (!timeZone) {
        return;
    }

    try {
        new Intl.DateTimeFormat(undefined, { timeZone });
        currentTimeZone = timeZone;
    } catch {
        // Unknown to this browser: keep its own timezone.
    }
}

export function getTimeZone(): string | undefined {
    return currentTimeZone;
}

/**
 * BCP 47 tag for Intl APIs and the `lang` attribute ("pt_BR" → "pt-BR").
 */
export function localeTag(locale = currentLocale): string {
    return locale.replace('_', '-');
}

function replace(text: string, replacements: Replacements): string {
    return Object.entries(replacements).reduce(
        (result, [key, value]) => result.replaceAll(`:${key}`, String(value)),
        text,
    );
}

/**
 * Translate a string: `t('Request :number', { number: '#3' })`.
 */
export function t(key: string, replacements: Replacements = {}): string {
    return replace(strings[key] || key, replacements);
}

/**
 * Pluralized translation with Laravel's "singular|plural" syntax:
 * `tChoice(':count ticket|:count tickets', 3)`.
 */
export function tChoice(
    key: string,
    count: number,
    replacements: Replacements = {},
): string {
    const variants = (strings[key] || key).split('|');
    const text =
        variants.length > 1 && count !== 1
            ? variants[variants.length - 1]
            : variants[0];

    return replace(text, { count, ...replacements });
}
