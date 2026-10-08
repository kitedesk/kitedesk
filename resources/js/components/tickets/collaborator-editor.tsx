import { router } from '@inertiajs/react';
import { X } from 'lucide-react';
import { useEffect, useState } from 'react';
import { useTranslation } from '@/hooks/use-translation';
import { cn } from '@/lib/utils';
import { search } from '@/routes/agent';
import { destroy, store } from '@/routes/agent/tickets/collaborators';
import type { UserSummary } from '@/types';

type Match = { id: number; name: string; email: string };

const EMAIL = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

/**
 * CC chips for the ticket panel. Pick an existing person or type an email address;
 * unknown addresses become customer accounts.
 */
export function CollaboratorEditor({
    ticketId,
    collaborators,
    disabled,
}: {
    ticketId: number;
    collaborators: UserSummary[];
    disabled?: boolean;
}) {
    const { t } = useTranslation();
    const [query, setQuery] = useState('');
    const [matches, setMatches] = useState<Match[]>([]);

    useEffect(() => {
        const term = query.trim();

        if (term.length < 2) {
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
                        users: Match[];
                    };
                    setMatches(
                        payload.users.filter(
                            (user) =>
                                !collaborators.some(
                                    (collaborator) =>
                                        collaborator.id === user.id,
                                ),
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
    }, [query, collaborators]);

    const options = {
        preserveScroll: true,
        preserveState: true,
        only: ['ticket', 'activity'],
        onSuccess: () => setQuery(''),
    };

    const add = (data: { user_id: number } | { email: string }) =>
        router.post(store.url(ticketId), data, options);

    return (
        <div className="relative">
            <div
                className={cn(
                    'flex min-h-9 flex-wrap items-center gap-1 rounded-md border bg-transparent px-1.5 py-1 shadow-xs dark:bg-input/30',
                    disabled && 'opacity-60',
                )}
            >
                {collaborators.map((collaborator) => (
                    <span
                        key={collaborator.id}
                        title={collaborator.email}
                        className="inline-flex items-center gap-0.5 rounded bg-muted py-0.5 pr-0.5 pl-1.5 text-xs"
                    >
                        {collaborator.name}
                        {!disabled && (
                            <button
                                type="button"
                                aria-label={t('Remove :name', {
                                    name: collaborator.name,
                                })}
                                onClick={() =>
                                    router.delete(
                                        destroy.url({
                                            ticket: ticketId,
                                            user: collaborator.id,
                                        }),
                                        options,
                                    )
                                }
                                className="rounded p-0.5 text-muted-foreground hover:bg-background hover:text-foreground"
                            >
                                <X className="size-3" />
                            </button>
                        )}
                    </span>
                ))}
                {!disabled && (
                    <input
                        value={query}
                        onChange={(event) => setQuery(event.target.value)}
                        onKeyDown={(event) => {
                            if (
                                event.key === 'Enter' &&
                                EMAIL.test(query.trim())
                            ) {
                                event.preventDefault();
                                add({ email: query.trim() });
                            }
                        }}
                        placeholder={
                            collaborators.length ? '' : t('Name or email…')
                        }
                        aria-label={t('Add people to CC')}
                        className="min-w-24 flex-1 bg-transparent px-1 text-sm outline-none placeholder:text-muted-foreground"
                    />
                )}
            </div>

            {query.trim().length >= 2 && (
                <div className="absolute z-20 mt-1 w-full overflow-hidden rounded-lg border bg-popover shadow-lg">
                    {matches.map((match) => (
                        <button
                            key={match.id}
                            type="button"
                            onClick={() => add({ user_id: match.id })}
                            className="flex w-full flex-col px-3 py-1.5 text-left text-sm hover:bg-accent"
                        >
                            <span className="truncate">{match.name}</span>
                            <span className="truncate text-xs text-muted-foreground">
                                {match.email}
                            </span>
                        </button>
                    ))}
                    {EMAIL.test(query.trim()) ? (
                        <button
                            type="button"
                            onClick={() => add({ email: query.trim() })}
                            className="w-full border-t px-3 py-1.5 text-left text-sm text-primary hover:bg-accent"
                        >
                            {t('Add :email', { email: query.trim() })}
                        </button>
                    ) : (
                        matches.length === 0 && (
                            <p className="px-3 py-1.5 text-xs text-muted-foreground">
                                {t(
                                    'Type a full email address to add someone new.',
                                )}
                            </p>
                        )
                    )}
                </div>
            )}
        </div>
    );
}
