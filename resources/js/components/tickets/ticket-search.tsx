import { useEffect, useState } from 'react';
import { StatusBadge } from '@/components/tickets/status-badge';
import { Input } from '@/components/ui/input';
import { useTranslation } from '@/hooks/use-translation';
import { search } from '@/routes/agent';
import type { CustomStatusSummary, TicketStatus } from '@/types';

export type TicketMatch = {
    id: number;
    number: string;
    subject: string;
    status: TicketStatus;
    custom_status?: CustomStatusSummary | null;
    requester_id: number;
};

/**
 * Find a ticket by number or subject, e.g. to merge or link it.
 */
export function TicketSearch({
    excludeIds,
    onSelect,
    autoFocus,
}: {
    excludeIds: number[];
    onSelect: (ticket: TicketMatch) => void;
    autoFocus?: boolean;
}) {
    const { t } = useTranslation();
    const [query, setQuery] = useState('');
    const [matches, setMatches] = useState<TicketMatch[]>([]);

    useEffect(() => {
        const term = query.trim();

        if (term.length < 2 && !/^#?\d+$/.test(term)) {
            setMatches([]);

            return;
        }

        const controller = new AbortController();

        const timeout = window.setTimeout(async () => {
            try {
                const response = await fetch(
                    search.url({ query: { q: term } }),
                    {
                        headers: { Accept: 'application/json' },
                        signal: controller.signal,
                    },
                );

                if (response.ok) {
                    const payload = (await response.json()) as {
                        tickets: TicketMatch[];
                    };
                    setMatches(
                        payload.tickets.filter(
                            (ticket) => !excludeIds.includes(ticket.id),
                        ),
                    );
                }
            } catch (error) {
                if ((error as Error).name !== 'AbortError') {
                    throw error;
                }
            }
        }, 200);

        // A newer query (or unmount) cancels the search still in flight.
        return () => {
            window.clearTimeout(timeout);
            controller.abort();
        };
    }, [query, excludeIds]);

    return (
        <div className="space-y-2">
            <Input
                value={query}
                onChange={(event) => setQuery(event.target.value)}
                placeholder={t('Ticket number or subject…')}
                autoFocus={autoFocus}
            />
            {matches.length > 0 && (
                <ul className="max-h-64 overflow-y-auto rounded-md border">
                    {matches.map((ticket) => (
                        <li key={ticket.id}>
                            <button
                                type="button"
                                onClick={() => onSelect(ticket)}
                                className="flex w-full items-center gap-2 px-3 py-2 text-left text-sm hover:bg-accent"
                            >
                                <StatusBadge
                                    status={ticket.status}
                                    customStatus={ticket.custom_status}
                                    className="shrink-0"
                                />
                                <span className="text-muted-foreground tabular-nums">
                                    {ticket.number}
                                </span>
                                <span className="truncate">
                                    {ticket.subject}
                                </span>
                            </button>
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}
