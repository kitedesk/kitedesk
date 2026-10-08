import { usePage } from '@inertiajs/react';
import { useEffect } from 'react';
import {
    localeTag,
    setTimeZone,
    setTranslations,
    t,
    tChoice,
} from '@/lib/i18n';

/**
 * Translation helpers for the active locale.
 *
 * ```tsx
 * const { t } = useTranslation();
 * return <h1>{t('Submit a request')}</h1>;
 * ```
 */
export function useTranslation() {
    const { locale, translations, auth } = usePage().props;

    setTranslations(locale, translations);
    setTimeZone(auth?.user?.timezone as string | null | undefined);

    const tag = localeTag(locale);

    useEffect(() => {
        document.documentElement.lang = tag;
    }, [tag]);

    return { t, tChoice, locale, localeTag: tag };
}
