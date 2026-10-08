import { ChevronsUp, ChevronUp, Equal, Minus } from 'lucide-react';
import { useTranslation } from '@/hooks/use-translation';
import { priorityLabel, priorityStyles } from '@/lib/tickets';
import { cn } from '@/lib/utils';
import type { TicketPriority } from '@/types';

const icons = {
    low: Minus,
    normal: Equal,
    high: ChevronUp,
    urgent: ChevronsUp,
} satisfies Record<TicketPriority, unknown>;

export function PriorityLabel({
    priority,
    className,
}: {
    priority: TicketPriority;
    className?: string;
}) {
    useTranslation();
    const Icon = icons[priority];

    return (
        <span
            className={cn(
                'inline-flex items-center gap-1 text-sm',
                priorityStyles[priority],
                className,
            )}
        >
            <Icon className="size-3.5" />
            {priorityLabel(priority)}
        </span>
    );
}
