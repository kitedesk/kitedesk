import { getTimeZone, localeTag, t } from '@/lib/i18n';
import type {
    CustomStatusSummary,
    StatusColor,
    TicketPriority,
    TicketSla,
    TicketStatus,
} from '@/types';

/**
 * English status names; render them with `statusLabel()` so they are translated.
 */
export const statusLabels: Record<TicketStatus, string> = {
    new: 'New',
    open: 'Open',
    pending: 'Pending',
    on_hold: 'On-hold',
    solved: 'Solved',
    closed: 'Closed',
};

export function statusLabel(status: TicketStatus): string {
    return t(statusLabels[status]);
}

/**
 * Zendesk-style status colors: a strong, recognizable hue per state.
 */
export const statusStyles: Record<TicketStatus, string> = {
    new: 'bg-amber-400/15 text-amber-700 ring-amber-500/30 dark:text-amber-300',
    open: 'bg-rose-500/15 text-rose-700 ring-rose-500/30 dark:text-rose-300',
    pending: 'bg-sky-500/15 text-sky-700 ring-sky-500/30 dark:text-sky-300',
    on_hold: 'bg-zinc-500/15 text-zinc-700 ring-zinc-500/30 dark:text-zinc-300',
    solved: 'bg-emerald-500/15 text-emerald-700 ring-emerald-500/30 dark:text-emerald-300',
    closed: 'bg-zinc-500/10 text-zinc-500 ring-zinc-500/20 dark:text-zinc-400',
};

export const statusDot: Record<TicketStatus, string> = {
    new: 'bg-amber-400',
    open: 'bg-rose-500',
    pending: 'bg-sky-500',
    on_hold: 'bg-zinc-500',
    solved: 'bg-emerald-500',
    closed: 'bg-zinc-400',
};

/**
 * Badge and dot classes for each color admins can give a custom status.
 */
export const statusColors: Record<StatusColor, { badge: string; dot: string }> =
    {
        slate: {
            badge: 'bg-slate-500/10 text-slate-600 ring-slate-500/20 dark:text-slate-400',
            dot: 'bg-slate-400',
        },
        zinc: {
            badge: 'bg-zinc-500/15 text-zinc-700 ring-zinc-500/30 dark:text-zinc-300',
            dot: 'bg-zinc-500',
        },
        amber: {
            badge: 'bg-amber-400/15 text-amber-700 ring-amber-500/30 dark:text-amber-300',
            dot: 'bg-amber-400',
        },
        orange: {
            badge: 'bg-orange-500/15 text-orange-700 ring-orange-500/30 dark:text-orange-300',
            dot: 'bg-orange-500',
        },
        rose: {
            badge: 'bg-rose-500/15 text-rose-700 ring-rose-500/30 dark:text-rose-300',
            dot: 'bg-rose-500',
        },
        violet: {
            badge: 'bg-violet-500/15 text-violet-700 ring-violet-500/30 dark:text-violet-300',
            dot: 'bg-violet-500',
        },
        blue: {
            badge: 'bg-blue-500/15 text-blue-700 ring-blue-500/30 dark:text-blue-300',
            dot: 'bg-blue-500',
        },
        sky: {
            badge: 'bg-sky-500/15 text-sky-700 ring-sky-500/30 dark:text-sky-300',
            dot: 'bg-sky-500',
        },
        teal: {
            badge: 'bg-teal-500/15 text-teal-700 ring-teal-500/30 dark:text-teal-300',
            dot: 'bg-teal-500',
        },
        emerald: {
            badge: 'bg-emerald-500/15 text-emerald-700 ring-emerald-500/30 dark:text-emerald-300',
            dot: 'bg-emerald-500',
        },
    };

/**
 * A custom status's name, or the category's when the status is unknown.
 */
export function customStatusLabel(
    status: TicketStatus,
    custom?: CustomStatusSummary | null,
): string {
    return custom?.name ?? statusLabel(status);
}

export const priorityLabels: Record<TicketPriority, string> = {
    low: 'Low',
    normal: 'Normal',
    high: 'High',
    urgent: 'Urgent',
};

export function priorityLabel(priority: TicketPriority): string {
    return t(priorityLabels[priority]);
}

export const priorityStyles: Record<TicketPriority, string> = {
    low: 'text-muted-foreground',
    normal: 'text-foreground',
    high: 'text-orange-600 dark:text-orange-400',
    urgent: 'text-red-600 dark:text-red-400',
};

const relativeFormatters = new Map<string, Intl.RelativeTimeFormat>();

function relativeFormatter(): Intl.RelativeTimeFormat {
    const tag = localeTag();

    if (!relativeFormatters.has(tag)) {
        relativeFormatters.set(
            tag,
            new Intl.RelativeTimeFormat(tag, {
                numeric: 'auto',
                style: 'short',
            }),
        );
    }

    return relativeFormatters.get(tag)!;
}

const units: [Intl.RelativeTimeFormatUnit, number][] = [
    ['year', 31_536_000],
    ['month', 2_592_000],
    ['week', 604_800],
    ['day', 86_400],
    ['hour', 3_600],
    ['minute', 60],
];

/**
 * "3 hr. ago", "in 20 min." — compact relative time for lists.
 */
export function relativeTime(
    iso: string | null | undefined,
    now = Date.now(),
): string {
    if (!iso) {
        return '—';
    }

    const seconds = Math.round((new Date(iso).getTime() - now) / 1000);

    for (const [unit, size] of units) {
        if (Math.abs(seconds) >= size) {
            return relativeFormatter().format(Math.round(seconds / size), unit);
        }
    }

    return t('just now');
}

export function formatDateTime(iso: string | null | undefined): string {
    return iso
        ? new Date(iso).toLocaleString(localeTag(), {
              dateStyle: 'medium',
              timeStyle: 'short',
              timeZone: getTimeZone(),
          })
        : '—';
}

export type SlaTarget = {
    label: string;
    dueAt: string;
};

/**
 * The SLA target that is due soonest, if any clock is running.
 */
export function nextSlaTarget(sla: TicketSla | undefined): SlaTarget | null {
    if (!sla) {
        return null;
    }

    const targets: SlaTarget[] = [
        { label: t('First reply'), dueAt: sla.first_response_due_at },
        { label: t('Next reply'), dueAt: sla.next_reply_due_at },
        { label: t('Resolution'), dueAt: sla.resolution_due_at },
    ].filter((target): target is SlaTarget => target.dueAt !== null);

    targets.sort(
        (a, b) => new Date(a.dueAt).getTime() - new Date(b.dueAt).getTime(),
    );

    return targets[0] ?? null;
}

export function formatFileSize(bytes: number): string {
    if (bytes < 1024) {
        return `${bytes} B`;
    }

    if (bytes < 1024 * 1024) {
        return `${(bytes / 1024).toFixed(1)} KB`;
    }

    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

/**
 * Render an arbitrary field value (custom fields, audit values) as text.
 */
export function displayValue(value: unknown): string {
    if (value === null || value === undefined) {
        return '';
    }

    if (typeof value === 'string') {
        return value;
    }

    if (typeof value === 'number' || typeof value === 'boolean') {
        return String(value);
    }

    return JSON.stringify(value);
}
