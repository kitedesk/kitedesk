import { localeTag, t } from '@/lib/i18n';
import type { SlaMetric } from '@/types/sla';

/**
 * Metric names in the active locale (getters, so they follow locale changes).
 */
export const metricLabels: Record<SlaMetric, string> = {
    get first_response() {
        return t('First reply');
    },
    get next_reply() {
        return t('Next reply');
    },
    get resolution() {
        return t('Resolution');
    },
};

export const metrics: SlaMetric[] = [
    'first_response',
    'next_reply',
    'resolution',
];

/**
 * Name of an ISO weekday (1 = Monday) in the active locale.
 */
function weekdayName(isoDay: number, style: 'long' | 'short'): string {
    // 1 January 2024 was a Monday.
    return new Intl.DateTimeFormat(localeTag(), {
        weekday: style,
        timeZone: 'UTC',
    }).format(new Date(Date.UTC(2024, 0, isoDay)));
}

/**
 * ISO weekdays with names in the active locale.
 */
export const weekdays: { key: string; label: string; short: string }[] = [
    1, 2, 3, 4, 5, 6, 7,
].map((isoDay) => ({
    key: String(isoDay),
    get label() {
        return weekdayName(isoDay, 'long');
    },
    get short() {
        return weekdayName(isoDay, 'short');
    },
}));

/**
 * "1d 2h", "3h 30m", "45m" — compact display of a minute target.
 */
export function formatMinutes(minutes: number | null | undefined): string {
    if (!minutes) {
        return '—';
    }

    const days = Math.floor(minutes / 1440);
    const hours = Math.floor((minutes % 1440) / 60);
    const rest = minutes % 60;

    return [days && `${days}d`, hours && `${hours}h`, rest && `${rest}m`]
        .filter(Boolean)
        .join(' ');
}
