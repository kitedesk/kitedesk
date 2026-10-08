import { useTranslation } from '@/hooks/use-translation';
import { statusColors, statusLabel, statusStyles } from '@/lib/tickets';
import { cn } from '@/lib/utils';
import type { CustomStatusSummary, TicketStatus } from '@/types';

/**
 * The ticket's status: the admin-defined status when known (staff), otherwise the category.
 */
export function StatusBadge({
    status,
    customStatus,
    className,
}: {
    status: TicketStatus;
    customStatus?: CustomStatusSummary | null;
    className?: string;
}) {
    useTranslation();

    return (
        <span
            className={cn(
                'inline-flex items-center rounded-md px-1.5 py-0.5 text-[11px] font-semibold tracking-wide uppercase ring-1 ring-inset',
                customStatus
                    ? statusColors[customStatus.color].badge
                    : statusStyles[status],
                className,
            )}
        >
            {customStatus?.name ?? statusLabel(status)}
        </span>
    );
}
