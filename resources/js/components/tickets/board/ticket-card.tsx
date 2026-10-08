import { Link } from '@inertiajs/react';
import { Lock } from 'lucide-react';
import { PriorityLabel } from '@/components/tickets/priority-label';
import { SlaTimer } from '@/components/tickets/sla-timer';
import { StatusBadge } from '@/components/tickets/status-badge';
import { UserAvatar } from '@/components/tickets/user-avatar';
import { useTranslation } from '@/hooks/use-translation';
import { relativeTime } from '@/lib/tickets';
import { cn } from '@/lib/utils';
import { show } from '@/routes/agent/tickets';
import type { BoardCardField, Ticket } from '@/types';

/**
 * A ticket on the board, showing only the fields the agent chose.
 */
export function TicketCard({
    ticket,
    fields,
    dragging = false,
    className,
}: {
    ticket: Ticket;
    fields: BoardCardField[];
    dragging?: boolean;
    className?: string;
}) {
    const { t } = useTranslation();
    const has = (field: BoardCardField) => fields.includes(field);
    const hasFooter =
        has('priority') || has('sla') || has('assignee') || has('updated');

    return (
        <div
            className={cn(
                'space-y-2 rounded-lg border bg-card p-3 text-sm shadow-xs transition-shadow',
                dragging && 'rotate-1 shadow-lg ring-2 ring-primary/30',
                className,
            )}
        >
            <Link
                href={show(ticket.id)}
                prefetch="click"
                className="line-clamp-2 font-medium hover:text-primary"
            >
                <span className="mr-1.5 text-muted-foreground tabular-nums">
                    {ticket.number}
                </span>
                {ticket.subject}
            </Link>

            {(has('requester') || has('group')) && (
                <p className="truncate text-xs text-muted-foreground">
                    {has('requester') && (
                        <span className="font-medium text-foreground/80">
                            {ticket.requester?.name}
                        </span>
                    )}
                    {has('requester') && has('group') && ticket.group && ' · '}
                    {has('group') && ticket.group?.name}
                </p>
            )}

            {has('latest_message') && ticket.latest_message && (
                <p className="line-clamp-2 text-xs text-muted-foreground">
                    {ticket.latest_message.is_internal && (
                        <Lock className="mr-1 inline size-3" />
                    )}
                    {ticket.latest_message.excerpt}
                </p>
            )}

            {(has('status') || (has('tags') && ticket.tags?.length)) && (
                <div className="flex flex-wrap items-center gap-1">
                    {has('status') && (
                        <StatusBadge
                            status={ticket.status}
                            customStatus={ticket.custom_status}
                        />
                    )}
                    {has('tags') &&
                        ticket.tags?.slice(0, 4).map((tag) => (
                            <span
                                key={tag}
                                className="rounded bg-muted px-1.5 py-0.5 text-[11px] text-muted-foreground"
                            >
                                {tag}
                            </span>
                        ))}
                </div>
            )}

            {hasFooter && (
                <div className="flex items-center gap-2 text-xs">
                    {has('priority') && (
                        <PriorityLabel
                            priority={ticket.priority}
                            className="text-xs"
                        />
                    )}
                    {has('sla') && <SlaTimer sla={ticket.sla} />}
                    <span className="ml-auto flex items-center gap-2 text-muted-foreground">
                        {has('updated') && relativeTime(ticket.updated_at)}
                        {has('assignee') &&
                            (ticket.assignee ? (
                                <UserAvatar
                                    src={ticket.assignee.avatar}
                                    name={ticket.assignee.name}
                                    className="size-5"
                                />
                            ) : (
                                <span title={t('Unassigned')}>—</span>
                            ))}
                    </span>
                </div>
            )}
        </div>
    );
}
