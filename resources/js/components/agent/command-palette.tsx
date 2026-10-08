import { router, usePage } from '@inertiajs/react';
import { Inbox, PlusCircle, Search, Settings, User } from 'lucide-react';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { StatusBadge } from '@/components/tickets/status-badge';
import { CommandMenu } from '@/components/ui/command-menu';
import type { CommandItem } from '@/components/ui/command-menu';
import { usePermissions } from '@/hooks/use-permissions';
import { useTranslation } from '@/hooks/use-translation';
import { search } from '@/routes/agent';
import { create, index, show } from '@/routes/agent/tickets';
import { edit as profileEdit } from '@/routes/profile';
import type { CustomStatusSummary, TicketStatus } from '@/types';

type SearchResults = {
    tickets: {
        id: number;
        number: string;
        subject: string;
        status: TicketStatus;
        custom_status?: CustomStatusSummary | null;
    }[];
    users: { id: number; name: string; email: string; type: string }[];
};

/**
 * Global ⌘K palette for the agent workspace: jump to views, tickets and people.
 */
export function CommandPalette() {
    const { agentNav } = usePage().props;
    const canCreateTickets = usePermissions().can('tickets.create');
    const { t } = useTranslation();
    const [isOpen, setIsOpen] = useState(false);
    const [results, setResults] = useState<SearchResults>({
        tickets: [],
        users: [],
    });
    const debounce = useRef<number | undefined>(undefined);
    const request = useRef<AbortController | null>(null);

    useEffect(() => {
        const onKeyDown = (event: KeyboardEvent) => {
            if (
                (event.metaKey || event.ctrlKey) &&
                event.key.toLowerCase() === 'k'
            ) {
                event.preventDefault();
                setIsOpen((open) => !open);
            }
        };

        const onOpenRequest = () => setIsOpen(true);

        window.addEventListener('keydown', onKeyDown);
        window.addEventListener('open-command-palette', onOpenRequest);

        return () => {
            window.removeEventListener('keydown', onKeyDown);
            window.removeEventListener('open-command-palette', onOpenRequest);
        };
    }, []);

    const runSearch = useCallback((query: string) => {
        window.clearTimeout(debounce.current);

        debounce.current = window.setTimeout(async () => {
            // A newer query replaces any search still in flight.
            request.current?.abort();

            if (query.trim().length < 2 && !/^#?\d+$/.test(query.trim())) {
                setResults({ tickets: [], users: [] });

                return;
            }

            const controller = new AbortController();
            request.current = controller;

            try {
                const response = await fetch(
                    search.url({ query: { q: query } }),
                    {
                        headers: { Accept: 'application/json' },
                        signal: controller.signal,
                    },
                );

                if (response.ok) {
                    setResults((await response.json()) as SearchResults);
                }
            } catch (error) {
                if ((error as Error).name !== 'AbortError') {
                    throw error;
                }
            }
        }, 180);
    }, []);

    const items = useMemo<CommandItem[]>(() => {
        const ticketItems: CommandItem[] = results.tickets.map((ticket) => ({
            id: `ticket-${ticket.id}`,
            title: `${ticket.number} ${ticket.subject}`,
            category: t('Tickets'),
            icon: (
                <StatusBadge
                    status={ticket.status}
                    customStatus={ticket.custom_status}
                    className="w-16 justify-center"
                />
            ),
            onSelect: () => router.visit(show(ticket.id)),
        }));

        const userItems: CommandItem[] = results.users.map((user) => ({
            id: `user-${user.id}`,
            title: `${user.name} <${user.email}>`,
            category: t('People'),
            icon: <User className="size-4" />,
            hint: canCreateTickets ? t('new ticket') : undefined,
            onSelect: () =>
                canCreateTickets
                    ? router.visit(create({ query: { requester_id: user.id } }))
                    : undefined,
        }));

        const viewItems: CommandItem[] = (agentNav?.views ?? []).map(
            (view) => ({
                id: `view-${view.key}`,
                title: view.label,
                category: t('Views'),
                icon: <Inbox className="size-4" />,
                shortcut: String(view.count),
                onSelect: () =>
                    router.visit(index({ query: { view: view.key } })),
            }),
        );

        return [
            ...ticketItems,
            ...userItems,
            ...(canCreateTickets
                ? [
                      {
                          id: 'new-ticket',
                          title: t('Create a new ticket'),
                          category: t('Actions'),
                          icon: <PlusCircle className="size-4" />,
                          shortcut: 'C',
                          onSelect: () => router.visit(create()),
                      },
                  ]
                : []),
            ...viewItems,
            {
                id: 'settings',
                title: t('Profile settings'),
                category: t('Navigation'),
                icon: <Settings className="size-4" />,
                onSelect: () => router.visit(profileEdit()),
            },
        ];
    }, [agentNav, canCreateTickets, results, t]);

    return (
        <CommandMenu
            isOpen={isOpen}
            onClose={() => setIsOpen(false)}
            items={items}
            placeholder={t('Search tickets by subject or #id, people, views…')}
            onQueryChange={runSearch}
            remoteCategories={[t('Tickets'), t('People')]}
            footerLabel="⌘K"
        />
    );
}

export function CommandPaletteTrigger() {
    const { t } = useTranslation();

    return (
        <button
            type="button"
            onClick={() =>
                window.dispatchEvent(new Event('open-command-palette'))
            }
            className="flex h-8 w-full max-w-72 items-center gap-2 rounded-md border bg-background px-2.5 text-sm text-muted-foreground shadow-xs transition-colors hover:bg-accent"
        >
            <Search className="size-3.5" />
            <span className="flex-1 text-left">{t('Search…')}</span>
            <kbd className="rounded border bg-muted px-1 font-mono text-[10px]">
                ⌘K
            </kbd>
        </button>
    );
}
