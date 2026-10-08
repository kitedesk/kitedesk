import { Head, Link, router, usePage } from '@inertiajs/react';
import { useEcho } from '@laravel/echo-react';
import { useChannelName } from '@/hooks/use-channel-name';
import {
    CheckCircle2,
    Clock3,
    Inbox,
    Kanban,
    List,
    Search,
    UserRoundCheck,
    X,
} from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import {
    ManageViewsButton,
    SavedViewSummary,
    SaveViewButton,
} from '@/components/agent/saved-views';
import type { SavedView } from '@/components/agent/saved-views';
import { BoardSettings } from '@/components/tickets/board/board-settings';
import { TicketBoard } from '@/components/tickets/board/ticket-board';
import { PriorityLabel } from '@/components/tickets/priority-label';
import { SlaTimer } from '@/components/tickets/sla-timer';
import { StatusBadge } from '@/components/tickets/status-badge';
import { UserAvatar } from '@/components/tickets/user-avatar';
import { DrawCheckbox } from '@/components/ui/draw-checkbox';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { SelectionBasket } from '@/components/ui/selection-basket';
import { SlidePagination } from '@/components/ui/slide-pagination';
import { useTranslation } from '@/hooks/use-translation';
import { scheduleReload } from '@/lib/live-reload';
import { postJson } from '@/lib/post-json';
import { relativeTime } from '@/lib/tickets';
import { cn } from '@/lib/utils';
import { board as saveBoardPreferences } from '@/routes/agent/preferences';
import { bulk, index, show } from '@/routes/agent/tickets';
import type {
    BoardPreferences,
    Paginated,
    Ticket,
    TicketBoardData,
    TicketOptions,
    TicketsLayout,
} from '@/types';

type Filters = {
    filter: Record<string, string>;
    sort: string | null;
};

type Props = {
    view: string;
    layout: TicketsLayout;
    savedView: SavedView | null;
    /** Only for the list layout. */
    tickets: Paginated<Ticket> | null;
    /** Only for the board layout. */
    board: TicketBoardData | null;
    boardPreferences: BoardPreferences;
    filters: Filters;
    options: TicketOptions;
};

const ANY = '__any';

export default function TicketsIndex({
    view,
    layout,
    savedView,
    tickets,
    board,
    boardPreferences,
    filters,
    options,
}: Props) {
    const { auth, agentNav } = usePage().props;
    const channelName = useChannelName();
    const { t, tChoice } = useTranslation();
    const [selected, setSelected] = useState<number[]>([]);
    const [search, setSearch] = useState(filters.filter?.search ?? '');
    const searchTimeout = useRef<number | undefined>(undefined);

    const viewLabel =
        agentNav?.views.find((item) => item.key === view)?.label ??
        t('Tickets');

    useEcho(
        channelName('staff.tickets'),
        ['.ticket.created', '.ticket.updated', '.message.created'],
        () => scheduleReload([layout === 'board' ? 'board' : 'tickets']),
    );

    useEffect(
        () => setSelected([]),
        [tickets?.meta.current_page, view, layout],
    );

    const total =
        tickets?.meta.total ??
        board?.lanes.reduce((sum, lane) => sum + lane.count, 0) ??
        0;

    const visit = (
        changes: Record<string, string | undefined>,
        page?: number,
        nextLayout: TicketsLayout = layout,
    ) => {
        const nextFilter = { ...filters.filter };

        Object.entries(changes).forEach(([key, value]) => {
            if (value === undefined || value === '' || value === ANY) {
                delete nextFilter[key];
            } else {
                nextFilter[key] = value;
            }
        });

        router.get(
            index.url(),
            {
                view,
                layout: nextLayout,
                ...(Object.keys(nextFilter).length
                    ? { filter: nextFilter }
                    : {}),
                ...(filters.sort ? { sort: filters.sort } : {}),
                ...(page && page > 1 ? { page } : {}),
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const onSearch = (value: string) => {
        setSearch(value);
        window.clearTimeout(searchTimeout.current);
        searchTimeout.current = window.setTimeout(
            () => visit({ search: value.trim() }),
            300,
        );
    };

    // Switching layouts also makes it the agent's default.
    const switchLayout = (next: TicketsLayout) => {
        if (next === layout) {
            return;
        }

        void postJson(saveBoardPreferences.url(), {
            _method: 'PATCH',
            tickets_layout: next,
        });
        visit({}, undefined, next);
    };

    const rows = tickets?.data ?? [];
    const allSelected = rows.length > 0 && selected.length === rows.length;

    const toggleAll = () =>
        setSelected(allSelected ? [] : rows.map((ticket) => ticket.id));

    const toggle = (id: number) =>
        setSelected((current) =>
            current.includes(id)
                ? current.filter((value) => value !== id)
                : [...current, id],
        );

    const bulkUpdate = (changes: Record<string, string | number>) =>
        router.patch(
            bulk.url(),
            { ids: selected, ...changes },
            {
                preserveScroll: true,
                only: ['tickets', 'agentNav'],
                onSuccess: () => setSelected([]),
            },
        );

    const activeFilterCount = useMemo(
        () =>
            Object.keys(filters.filter ?? {}).filter((key) => key !== 'search')
                .length,
        [filters.filter],
    );

    return (
        <>
            <Head title={viewLabel} />

            <div className="flex flex-1 flex-col gap-4 p-4 md:p-6">
                <div className="flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <h1 className="text-xl font-semibold tracking-tight">
                            {viewLabel}
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {tChoice(':count ticket|:count tickets', total)}
                        </p>
                        {savedView && (
                            <SavedViewSummary
                                savedView={savedView}
                                options={options}
                            />
                        )}
                    </div>

                    <div className="relative w-full sm:w-72">
                        <Search className="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            value={search}
                            onChange={(event) => onSearch(event.target.value)}
                            placeholder={t(
                                'Filter by subject, #id, requester…',
                            )}
                            className="pl-8"
                        />
                    </div>
                </div>

                <div className="flex flex-wrap items-center gap-2">
                    <FilterSelect
                        label={t('Status')}
                        value={filters.filter?.ticket_status_id}
                        options={options.customStatuses
                            .filter((status) => status.is_active)
                            .map((status) => ({
                                value: String(status.id),
                                label: status.name,
                            }))}
                        onChange={(value) =>
                            visit({
                                ticket_status_id: value,
                                status: undefined,
                            })
                        }
                    />
                    <FilterSelect
                        label={t('Priority')}
                        value={filters.filter?.priority}
                        options={options.priorities.map((priority) => ({
                            value: priority.value,
                            label: priority.label,
                        }))}
                        onChange={(value) => visit({ priority: value })}
                    />
                    <FilterSelect
                        label={t('Assignee')}
                        value={filters.filter?.assignee_id}
                        options={options.agents.map((agent) => ({
                            value: String(agent.id),
                            label: agent.name,
                        }))}
                        onChange={(value) => visit({ assignee_id: value })}
                    />
                    <FilterSelect
                        label={t('Group')}
                        value={filters.filter?.group_id}
                        options={options.groups.map((group) => ({
                            value: String(group.id),
                            label: group.name,
                        }))}
                        onChange={(value) => visit({ group_id: value })}
                    />
                    {options.categories.length > 0 && (
                        <FilterSelect
                            label={t('Category')}
                            value={filters.filter?.category_id}
                            options={options.categories.map((category) => ({
                                value: String(category.id),
                                label: category.name,
                            }))}
                            onChange={(value) => visit({ category_id: value })}
                        />
                    )}
                    {activeFilterCount > 0 && (
                        <button
                            type="button"
                            onClick={() =>
                                visit({
                                    status: undefined,
                                    ticket_status_id: undefined,
                                    priority: undefined,
                                    assignee_id: undefined,
                                    group_id: undefined,
                                    category_id: undefined,
                                })
                            }
                            className="inline-flex items-center gap-1 rounded-md px-2 py-1 text-xs text-muted-foreground hover:bg-accent hover:text-foreground"
                        >
                            <X className="size-3" /> {t('Clear filters')}
                        </button>
                    )}
                    <div className="ml-auto flex items-center gap-1">
                        {board && (
                            <BoardSettings
                                board={board}
                                preferences={boardPreferences}
                            />
                        )}
                        <LayoutToggle value={layout} onChange={switchLayout} />
                        <SaveViewButton
                            view={view}
                            filter={filters.filter ?? {}}
                            sort={filters.sort ?? undefined}
                            layout={layout}
                        />
                        <ManageViewsButton />
                    </div>
                </div>

                {board && (
                    <TicketBoard
                        board={board}
                        cardFields={boardPreferences.card_fields}
                        query={{
                            view,
                            filter: filters.filter,
                            sort: filters.sort ?? undefined,
                        }}
                    />
                )}

                {tickets && (
                    <>
                        <div className="overflow-hidden rounded-xl border bg-card shadow-xs">
                            {tickets.data.length === 0 ? (
                                <EmptyState />
                            ) : (
                                <div className="overflow-x-auto">
                                    <table className="w-full min-w-[56rem] text-sm">
                                        <thead>
                                            <tr className="border-b bg-muted/40 text-left text-xs font-medium text-muted-foreground">
                                                <th className="w-10 py-2.5 pl-4">
                                                    <DrawCheckbox
                                                        aria-label={t(
                                                            'Select all tickets',
                                                        )}
                                                        checked={allSelected}
                                                        indeterminate={
                                                            selected.length >
                                                                0 &&
                                                            !allSelected
                                                        }
                                                        onChange={toggleAll}
                                                    />
                                                </th>
                                                <th className="py-2.5 pr-3 font-medium">
                                                    {t('Subject')}
                                                </th>
                                                <th className="px-3 py-2.5 font-medium">
                                                    {t('Status')}
                                                </th>
                                                <th className="px-3 py-2.5 font-medium">
                                                    {t('Priority')}
                                                </th>
                                                <th className="px-3 py-2.5 font-medium">
                                                    {t('Assignee')}
                                                </th>
                                                <th className="px-3 py-2.5 font-medium">
                                                    {t('SLA')}
                                                </th>
                                                <th className="px-4 py-2.5 text-right font-medium">
                                                    {t('Updated')}
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {tickets.data.map((ticket) => (
                                                <tr
                                                    key={ticket.id}
                                                    onClick={() =>
                                                        router.visit(
                                                            show(ticket.id),
                                                        )
                                                    }
                                                    className={cn(
                                                        'group cursor-pointer border-b transition-colors last:border-0 hover:bg-muted/50',
                                                        selected.includes(
                                                            ticket.id,
                                                        ) &&
                                                            'bg-primary/5 hover:bg-primary/10',
                                                    )}
                                                >
                                                    <td
                                                        className="py-3 pl-4 align-top"
                                                        onClick={(event) =>
                                                            event.stopPropagation()
                                                        }
                                                    >
                                                        <DrawCheckbox
                                                            aria-label={t(
                                                                'Select ticket :number',
                                                                {
                                                                    number: ticket.number,
                                                                },
                                                            )}
                                                            checked={selected.includes(
                                                                ticket.id,
                                                            )}
                                                            onChange={() =>
                                                                toggle(
                                                                    ticket.id,
                                                                )
                                                            }
                                                        />
                                                    </td>
                                                    <td className="max-w-0 py-3 pr-3">
                                                        <Link
                                                            href={show(
                                                                ticket.id,
                                                            )}
                                                            prefetch="click"
                                                            onClick={(event) =>
                                                                event.stopPropagation()
                                                            }
                                                            className="block truncate font-medium group-hover:text-primary"
                                                        >
                                                            <span className="mr-1.5 text-muted-foreground tabular-nums">
                                                                {ticket.number}
                                                            </span>
                                                            {ticket.subject}
                                                        </Link>
                                                        <p className="mt-0.5 truncate text-xs text-muted-foreground">
                                                            <span className="font-medium text-foreground/80">
                                                                {
                                                                    ticket
                                                                        .requester
                                                                        ?.name
                                                                }
                                                            </span>
                                                            {ticket.category && (
                                                                <>
                                                                    {' · '}
                                                                    <span className="text-foreground/70">
                                                                        {
                                                                            ticket
                                                                                .category
                                                                                .path
                                                                        }
                                                                    </span>
                                                                </>
                                                            )}
                                                            {ticket.latest_message && (
                                                                <>
                                                                    {' · '}
                                                                    {ticket
                                                                        .latest_message
                                                                        .is_internal &&
                                                                        '🔒 '}
                                                                    {
                                                                        ticket
                                                                            .latest_message
                                                                            .excerpt
                                                                    }
                                                                </>
                                                            )}
                                                        </p>
                                                        {ticket.tags &&
                                                            ticket.tags.length >
                                                                0 && (
                                                                <div className="mt-1.5 flex flex-wrap gap-1">
                                                                    {ticket.tags
                                                                        .slice(
                                                                            0,
                                                                            4,
                                                                        )
                                                                        .map(
                                                                            (
                                                                                tag,
                                                                            ) => (
                                                                                <span
                                                                                    key={
                                                                                        tag
                                                                                    }
                                                                                    className="rounded bg-muted px-1.5 py-0.5 text-[11px] text-muted-foreground"
                                                                                >
                                                                                    {
                                                                                        tag
                                                                                    }
                                                                                </span>
                                                                            ),
                                                                        )}
                                                                </div>
                                                            )}
                                                    </td>
                                                    <td className="px-3 py-3 align-top">
                                                        <StatusBadge
                                                            status={
                                                                ticket.status
                                                            }
                                                            customStatus={
                                                                ticket.custom_status
                                                            }
                                                        />
                                                    </td>
                                                    <td className="px-3 py-3 align-top">
                                                        <PriorityLabel
                                                            priority={
                                                                ticket.priority
                                                            }
                                                        />
                                                    </td>
                                                    <td className="px-3 py-3 align-top">
                                                        {ticket.assignee ? (
                                                            <span className="flex items-center gap-2">
                                                                <UserAvatar
                                                                    src={
                                                                        ticket
                                                                            .assignee
                                                                            .avatar
                                                                    }
                                                                    name={
                                                                        ticket
                                                                            .assignee
                                                                            .name
                                                                    }
                                                                    className="size-6"
                                                                />
                                                                <span className="truncate">
                                                                    {
                                                                        ticket
                                                                            .assignee
                                                                            .name
                                                                    }
                                                                </span>
                                                            </span>
                                                        ) : (
                                                            <span className="text-muted-foreground">
                                                                {ticket.group
                                                                    ?.name ??
                                                                    t(
                                                                        'Unassigned',
                                                                    )}
                                                            </span>
                                                        )}
                                                    </td>
                                                    <td className="px-3 py-3 align-top">
                                                        <SlaTimer
                                                            sla={ticket.sla}
                                                        />
                                                    </td>
                                                    <td className="px-4 py-3 text-right align-top text-xs whitespace-nowrap text-muted-foreground">
                                                        {relativeTime(
                                                            ticket.updated_at,
                                                        )}
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            )}
                        </div>

                        {tickets.meta.last_page > 1 && (
                            <div className="flex justify-center">
                                <SlidePagination
                                    pageCount={tickets.meta.last_page}
                                    page={tickets.meta.current_page}
                                    onChange={(page) => visit({}, page)}
                                />
                            </div>
                        )}
                    </>
                )}
            </div>

            <SelectionBasket
                selectedCount={selected.length}
                totalCount={rows.length}
                onClearSelection={() => setSelected([])}
                onSelectAll={toggleAll}
                actions={[
                    {
                        id: 'assign-me',
                        label: t('Assign to me'),
                        icon: <UserRoundCheck className="size-4" />,
                        onClick: () =>
                            bulkUpdate({ assignee_id: auth.user.id }),
                    },
                    {
                        id: 'pending',
                        label: t('Pending'),
                        icon: <Clock3 className="size-4" />,
                        onClick: () => bulkUpdate({ status: 'pending' }),
                    },
                    {
                        id: 'solve',
                        label: t('Solve'),
                        variant: 'primary',
                        icon: <CheckCircle2 className="size-4" />,
                        onClick: () => bulkUpdate({ status: 'solved' }),
                    },
                ]}
            />
        </>
    );
}

function LayoutToggle({
    value,
    onChange,
}: {
    value: TicketsLayout;
    onChange: (layout: TicketsLayout) => void;
}) {
    const { t } = useTranslation();
    const choices = [
        { value: 'list' as const, label: t('List'), icon: List },
        { value: 'board' as const, label: t('Board'), icon: Kanban },
    ];

    return (
        <div
            role="radiogroup"
            aria-label={t('Layout')}
            className="flex h-8 items-center rounded-md border p-0.5"
        >
            {choices.map((choice) => (
                <button
                    key={choice.value}
                    type="button"
                    role="radio"
                    aria-checked={value === choice.value}
                    title={choice.label}
                    onClick={() => onChange(choice.value)}
                    className={cn(
                        'inline-flex h-full items-center gap-1 rounded px-2 text-xs text-muted-foreground hover:text-foreground',
                        value === choice.value &&
                            'bg-accent font-medium text-foreground',
                    )}
                >
                    <choice.icon className="size-3.5" />
                    <span className="hidden sm:inline">{choice.label}</span>
                </button>
            ))}
        </div>
    );
}

function FilterSelect({
    label,
    value,
    options,
    onChange,
}: {
    label: string;
    value?: string;
    options: { value: string; label: string }[];
    onChange: (value: string) => void;
}) {
    const { t } = useTranslation();

    return (
        <Select value={value ?? ANY} onValueChange={onChange}>
            <SelectTrigger
                size="sm"
                className={cn(
                    'h-8 min-w-32 text-xs',
                    value && 'border-primary/50 bg-primary/5',
                )}
            >
                <span className="text-muted-foreground">{label}:</span>
                <SelectValue />
            </SelectTrigger>
            <SelectContent>
                <SelectItem value={ANY}>{t('Any')}</SelectItem>
                {options.map((option) => (
                    <SelectItem key={option.value} value={option.value}>
                        {option.label}
                    </SelectItem>
                ))}
            </SelectContent>
        </Select>
    );
}

function EmptyState() {
    const { t } = useTranslation();

    return (
        <div className="flex flex-col items-center justify-center gap-2 px-6 py-20 text-center">
            <div className="rounded-full bg-primary/10 p-3 text-primary">
                <Inbox className="size-6" />
            </div>
            <h2 className="font-medium">{t('Nothing here')}</h2>
            <p className="max-w-sm text-sm text-muted-foreground">
                {t(
                    'No tickets match this view and filters. Enjoy the calm — or clear the filters to look wider.',
                )}
            </p>
        </div>
    );
}
