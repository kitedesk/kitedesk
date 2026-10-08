import { useTranslation } from '@/hooks/use-translation';
import { cn } from '@/lib/utils';
import type { RunStatus } from '@/types';

const STYLES: Record<RunStatus, string> = {
    running: 'bg-sky-500/15 text-sky-700 dark:text-sky-300',
    waiting: 'bg-amber-500/15 text-amber-700 dark:text-amber-300',
    completed: 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300',
    stopped: 'bg-zinc-500/15 text-zinc-700 dark:text-zinc-300',
    failed: 'bg-destructive/15 text-destructive',
    cancelled: 'bg-zinc-500/15 text-zinc-700 dark:text-zinc-300',
};

const LABELS: Record<RunStatus, string> = {
    running: 'Running',
    waiting: 'Waiting',
    completed: 'Completed',
    stopped: 'Stopped',
    failed: 'Failed',
    cancelled: 'Cancelled',
};

export function RunStatusBadge({
    status,
    className,
}: {
    status: RunStatus;
    className?: string;
}) {
    const { t } = useTranslation();

    return (
        <span
            className={cn(
                'inline-flex rounded-full px-2 py-0.5 text-[11px] font-medium',
                STYLES[status],
                className,
            )}
        >
            {t(LABELS[status])}
        </span>
    );
}
