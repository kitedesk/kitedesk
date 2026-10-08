import { Link, router } from '@inertiajs/react';
import { Link2, Plus, X } from 'lucide-react';
import { useState } from 'react';
import { StatusBadge } from '@/components/tickets/status-badge';
import { TicketSearch } from '@/components/tickets/ticket-search';
import { Button } from '@/components/ui/button';
import { useTranslation } from '@/hooks/use-translation';
import { show } from '@/routes/agent/tickets';
import { destroy, store } from '@/routes/agent/tickets/links';
import type { CustomStatusSummary, TicketStatus } from '@/types';

export type LinkedTicket = {
    id: number;
    number: string;
    subject: string;
    status: TicketStatus;
    custom_status?: CustomStatusSummary | null;
};

/**
 * Tickets marked as related to this one, with add/remove.
 */
export function RelatedTickets({
    ticketId,
    tickets,
    disabled,
}: {
    ticketId: number;
    tickets: LinkedTicket[];
    disabled?: boolean;
}) {
    const { t } = useTranslation();
    const [adding, setAdding] = useState(false);
    const options = {
        preserveScroll: true,
        preserveState: true,
        only: ['linkedTickets'],
    };

    return (
        <section className="space-y-2">
            <div className="flex items-center justify-between">
                <h2 className="flex items-center gap-1.5 text-xs font-medium tracking-wide text-muted-foreground uppercase">
                    <Link2 className="size-3.5" /> {t('Related tickets')}
                </h2>
                {!disabled && !adding && (
                    <Button
                        variant="ghost"
                        size="icon"
                        className="size-6"
                        aria-label={t('Link a ticket')}
                        onClick={() => setAdding(true)}
                    >
                        <Plus />
                    </Button>
                )}
            </div>

            {adding && (
                <TicketSearch
                    excludeIds={[
                        ticketId,
                        ...tickets.map((ticket) => ticket.id),
                    ]}
                    autoFocus
                    onSelect={(linked) =>
                        router.post(
                            store.url(ticketId),
                            { linked_id: linked.id },
                            { ...options, onSuccess: () => setAdding(false) },
                        )
                    }
                />
            )}

            {tickets.length === 0 && !adding ? (
                <p className="text-xs text-muted-foreground">
                    {t('No related tickets.')}
                </p>
            ) : (
                <ul className="space-y-1">
                    {tickets.map((linked) => (
                        <li
                            key={linked.id}
                            className="group flex items-center gap-2 text-sm"
                        >
                            <StatusBadge
                                status={linked.status}
                                customStatus={linked.custom_status}
                                className="shrink-0"
                            />
                            <Link
                                href={show(linked.id)}
                                className="min-w-0 flex-1 truncate hover:underline"
                            >
                                {linked.number} {linked.subject}
                            </Link>
                            {!disabled && (
                                <button
                                    type="button"
                                    aria-label={t('Unlink :number', {
                                        number: linked.number,
                                    })}
                                    onClick={() =>
                                        router.delete(
                                            destroy.url({
                                                ticket: ticketId,
                                                linked: linked.id,
                                            }),
                                            options,
                                        )
                                    }
                                    className="rounded p-0.5 text-muted-foreground opacity-0 group-hover:opacity-100 hover:bg-accent focus:opacity-100"
                                >
                                    <X className="size-3.5" />
                                </button>
                            )}
                        </li>
                    ))}
                </ul>
            )}
        </section>
    );
}
