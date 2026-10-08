import {
    DndContext,
    DragOverlay,
    KeyboardSensor,
    PointerSensor,
    TouchSensor,
    useDraggable,
    useDroppable,
    useSensor,
    useSensors,
} from '@dnd-kit/core';
import type { DragEndEvent, DragStartEvent } from '@dnd-kit/core';
import { router, usePage } from '@inertiajs/react';
import { Inbox, Loader2 } from 'lucide-react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import { TicketCard } from '@/components/tickets/board/ticket-card';
import { Button } from '@/components/ui/button';
import { useTranslation } from '@/hooks/use-translation';
import { statusColors } from '@/lib/tickets';
import { cn } from '@/lib/utils';
import { lane as laneRoute } from '@/routes/agent/tickets/board';
import { update } from '@/routes/agent/tickets';
import type {
    BoardCardField,
    BoardGroupBy,
    BoardLane,
    Ticket,
    TicketBoardData,
} from '@/types';

/**
 * The ticket property a lane sets when a card is dropped on it.
 */
function changeFor(
    groupBy: BoardGroupBy,
    key: string,
): Record<string, string | number | null> {
    const id = key === 'none' ? null : Number(key);

    switch (groupBy) {
        case 'status':
            return { ticket_status_id: Number(key) };
        case 'priority':
            return { priority: key };
        case 'assignee':
            return { assignee_id: id };
        case 'group':
            return { group_id: id };
    }
}

/**
 * The board after moving a ticket to another lane, newest first in its new lane.
 */
function moveTicket(
    board: TicketBoardData,
    ticket: Ticket,
    from: string,
    to: string,
): TicketBoardData {
    return {
        ...board,
        lanes: board.lanes.map((lane) => {
            if (lane.key === from) {
                return {
                    ...lane,
                    count: Math.max(0, lane.count - 1),
                    tickets: lane.tickets.filter(
                        (item) => item.id !== ticket.id,
                    ),
                };
            }

            if (lane.key === to) {
                return {
                    ...lane,
                    count: lane.count + 1,
                    tickets: [ticket, ...lane.tickets],
                };
            }

            return lane;
        }),
    };
}

type Query = {
    view: string;
    filter?: Record<string, string>;
    sort?: string;
};

/**
 * Tickets as kanban lanes. Dropping a card on another lane changes the ticket; the move
 * shows right away and is undone if the server refuses it.
 */
export function TicketBoard({
    board,
    cardFields,
    query,
}: {
    board: TicketBoardData;
    cardFields: BoardCardField[];
    query: Query;
}) {
    const { t } = useTranslation();
    const { auth } = usePage().props;
    const canUpdate = auth.can['tickets.update'] === true;
    const [active, setActive] = useState<{
        ticket: Ticket;
        from: string;
    } | null>(null);
    // Cards loaded with "Load more", until the board reloads.
    const [extra, setExtra] = useState<
        Record<string, { tickets: Ticket[]; hasMore: boolean }>
    >({});

    useEffect(() => setExtra({}), [board]);

    const sensors = useSensors(
        useSensor(PointerSensor, { activationConstraint: { distance: 6 } }),
        useSensor(TouchSensor, {
            activationConstraint: { delay: 200, tolerance: 6 },
        }),
        useSensor(KeyboardSensor),
    );

    const onDragStart = (event: DragStartEvent) => {
        const data = event.active.data.current as
            | { ticket: Ticket; from: string }
            | undefined;
        setActive(data ?? null);
    };

    const onDragEnd = (event: DragEndEvent) => {
        setActive(null);
        const data = event.active.data.current as
            | { ticket: Ticket; from: string }
            | undefined;
        const to = event.over?.id ? String(event.over.id) : null;

        if (!data || !to || to === data.from) {
            return;
        }

        const { ticket, from } = data;

        setExtra((current) =>
            current[from]
                ? {
                      ...current,
                      [from]: {
                          ...current[from],
                          tickets: current[from].tickets.filter(
                              (item) => item.id !== ticket.id,
                          ),
                      },
                  }
                : current,
        );

        router
            .optimistic((props) => ({
                board: moveTicket(
                    (props as { board: TicketBoardData }).board,
                    ticket,
                    from,
                    to,
                ),
            }))
            .patch(update.url(ticket.id), changeFor(board.group_by, to), {
                only: ['board', 'agentNav'],
                preserveScroll: true,
                preserveState: true,
            });
    };

    const loadMore = async (lane: BoardLane) => {
        const loaded =
            lane.tickets.length + (extra[lane.key]?.tickets.length ?? 0);
        const params = new URLSearchParams({
            view: query.view,
            lane: lane.key,
            offset: String(loaded),
        });

        Object.entries(query.filter ?? {}).forEach(([key, value]) =>
            params.set(`filter[${key}]`, value),
        );

        if (query.sort) {
            params.set('sort', query.sort);
        }

        const response = await fetch(`${laneRoute.url()}?${params}`, {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        });

        if (!response.ok) {
            toast.error(t('Something went wrong.'));

            return;
        }

        const data = (await response.json()) as {
            tickets: Ticket[];
            has_more: boolean;
        };

        setExtra((current) => ({
            ...current,
            [lane.key]: {
                tickets: [
                    ...(current[lane.key]?.tickets ?? []),
                    ...data.tickets,
                ],
                hasMore: data.has_more,
            },
        }));
    };

    if (board.lanes.length === 0) {
        return (
            <div className="flex flex-col items-center gap-2 rounded-xl border border-dashed px-6 py-16 text-center text-sm text-muted-foreground">
                <Inbox className="size-6" />
                {t('Every lane is hidden. Show some with “Customize”.')}
            </div>
        );
    }

    return (
        <DndContext
            sensors={sensors}
            onDragStart={onDragStart}
            onDragEnd={onDragEnd}
            onDragCancel={() => setActive(null)}
        >
            <div className="-mx-4 flex snap-x snap-mandatory gap-3 overflow-x-auto px-4 pb-2 md:mx-0 md:px-0">
                {board.lanes.map((lane) => (
                    <Lane
                        key={lane.key}
                        lane={lane}
                        extra={extra[lane.key]}
                        cardFields={cardFields}
                        canDrag={canUpdate}
                        onLoadMore={() => loadMore(lane)}
                    />
                ))}
            </div>

            <DragOverlay>
                {active && (
                    <TicketCard
                        ticket={active.ticket}
                        fields={cardFields}
                        dragging
                    />
                )}
            </DragOverlay>
        </DndContext>
    );
}

function Lane({
    lane,
    extra,
    cardFields,
    canDrag,
    onLoadMore,
}: {
    lane: BoardLane;
    extra?: { tickets: Ticket[]; hasMore: boolean };
    cardFields: BoardCardField[];
    canDrag: boolean;
    onLoadMore: () => Promise<void>;
}) {
    const { t } = useTranslation();
    const { setNodeRef, isOver } = useDroppable({ id: lane.key });
    const [loading, setLoading] = useState(false);
    const tickets = [...lane.tickets, ...(extra?.tickets ?? [])];
    const hasMore = extra ? extra.hasMore : lane.has_more;

    return (
        <section
            ref={setNodeRef}
            aria-label={lane.label}
            className={cn(
                'flex max-h-[calc(100vh-14rem)] min-h-40 w-72 shrink-0 snap-start flex-col rounded-xl border bg-muted/40 transition-colors',
                isOver && 'border-primary/50 bg-primary/5',
            )}
        >
            <header className="flex items-center gap-2 px-3 py-2.5 text-sm font-medium">
                {lane.color && (
                    <span
                        className={cn(
                            'size-2 rounded-full',
                            statusColors[lane.color].dot,
                        )}
                    />
                )}
                <span className="truncate">{lane.label}</span>
                <span className="ml-auto rounded-full bg-background px-2 text-xs text-muted-foreground tabular-nums">
                    {lane.count}
                </span>
            </header>

            <div className="flex flex-1 flex-col gap-2 overflow-y-auto px-2 pb-2">
                {tickets.map((ticket) => (
                    <DraggableCard
                        key={ticket.id}
                        ticket={ticket}
                        lane={lane.key}
                        fields={cardFields}
                        disabled={!canDrag || ticket.status === 'closed'}
                    />
                ))}

                {tickets.length === 0 && (
                    <p className="px-2 py-6 text-center text-xs text-muted-foreground">
                        {t('No tickets')}
                    </p>
                )}

                {hasMore && (
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        disabled={loading}
                        onClick={() => {
                            setLoading(true);
                            void onLoadMore().finally(() => setLoading(false));
                        }}
                    >
                        {loading && <Loader2 className="animate-spin" />}
                        {t('Load more')}
                    </Button>
                )}
            </div>
        </section>
    );
}

function DraggableCard({
    ticket,
    lane,
    fields,
    disabled,
}: {
    ticket: Ticket;
    lane: string;
    fields: BoardCardField[];
    disabled: boolean;
}) {
    const { attributes, listeners, setNodeRef, isDragging } = useDraggable({
        id: ticket.id,
        data: { ticket, from: lane },
        disabled,
    });

    return (
        <div
            ref={setNodeRef}
            {...listeners}
            {...attributes}
            className={cn(
                'touch-manipulation outline-none focus-visible:rounded-lg focus-visible:ring-2 focus-visible:ring-ring',
                !disabled && 'cursor-grab active:cursor-grabbing',
                isDragging && 'opacity-40',
            )}
        >
            <TicketCard ticket={ticket} fields={fields} />
        </div>
    );
}
