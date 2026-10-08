import { Clock, Pause } from 'lucide-react';
import { useNow } from '@/hooks/use-now';
import { useTranslation } from '@/hooks/use-translation';
import { formatDateTime, nextSlaTarget } from '@/lib/tickets';
import { cn } from '@/lib/utils';
import type { TicketSla } from '@/types';

function formatRemaining(ms: number): string {
    const minutes = Math.round(Math.abs(ms) / 60_000);

    if (minutes < 60) {
        return `${minutes}m`;
    }

    const hours = Math.floor(minutes / 60);

    if (hours < 48) {
        return `${hours}h ${minutes % 60}m`;
    }

    return `${Math.floor(hours / 24)}d`;
}

/**
 * Live countdown to the nearest SLA target; turns amber in the last hour and red once breached.
 */
export function SlaTimer({
    sla,
    showLabel = false,
    className,
}: {
    sla?: TicketSla;
    showLabel?: boolean;
    className?: string;
}) {
    const { t } = useTranslation();
    const now = useNow();

    const target = nextSlaTarget(sla);

    if (!target) {
        return sla?.resolution_paused ? (
            <span
                className={cn(
                    'inline-flex items-center gap-1 text-xs text-muted-foreground',
                    className,
                )}
            >
                <Pause className="size-3" /> {t('Paused')}
            </span>
        ) : (
            <span className={cn('text-xs text-muted-foreground', className)}>
                —
            </span>
        );
    }

    const remaining = new Date(target.dueAt).getTime() - now;
    const breached = remaining <= 0;
    const warning = !breached && remaining < 60 * 60_000;

    return (
        <span
            title={t(':target due :date', {
                target: target.label,
                date: formatDateTime(target.dueAt),
            })}
            className={cn(
                'inline-flex items-center gap-1 rounded-md px-1.5 py-0.5 text-xs font-medium tabular-nums',
                breached && 'bg-red-500/15 text-red-700 dark:text-red-300',
                warning && 'bg-amber-500/15 text-amber-700 dark:text-amber-300',
                !breached && !warning && 'text-muted-foreground',
                className,
            )}
        >
            <Clock className="size-3" />
            {showLabel && <span>{target.label}</span>}
            {breached
                ? `-${formatRemaining(remaining)}`
                : formatRemaining(remaining)}
        </span>
    );
}
