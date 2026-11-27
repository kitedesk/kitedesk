import { Link, router, useHttp } from '@inertiajs/react';
import { ChevronDown, ExternalLink, Link2, Plus, X } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { usePermissions } from '@/hooks/use-permissions';
import { useTranslation } from '@/hooks/use-translation';
import type { TicketPanelProps } from '@/lib/extensions';
import { cn } from '@/lib/utils';
import { edit as jiraSettings } from '@/routes/admin/jira';
import {
    destroy,
    index,
    issueTypes as issueTypesRoute,
    link,
    projects as projectsRoute,
    search as searchRoute,
    store,
    transition as transitionRoute,
    transitions as transitionsRoute,
} from '@/routes/agent/tickets/jira';

type StatusCategory = 'new' | 'indeterminate' | 'done';

type LinkedIssue = {
    id: number;
    key: string;
    summary: string;
    type: string | null;
    status: string | null;
    status_category: StatusCategory | null;
    resolution: string | null;
    assignee: string | null;
    url: string;
    deleted: boolean;
};

type PanelData = {
    connected: boolean;
    error: string | null;
    can_manage: boolean;
    defaults: { project: string | null; issue_type: string | null };
    issues: LinkedIssue[];
};

type SearchResult = {
    key: string;
    summary: string;
    status: string | null;
    status_category: StatusCategory | null;
};

type Body = Record<string, string>;

const categoryStyles: Record<StatusCategory, string> = {
    new: 'bg-muted text-muted-foreground',
    indeterminate: 'bg-blue-500/15 text-blue-700 dark:text-blue-300',
    done: 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300',
};

export function StatusBadge({
    status,
    category,
}: {
    status: string | null;
    category: StatusCategory | null;
}) {
    if (!status) {
        return null;
    }

    return (
        <span
            className={cn(
                'rounded px-1.5 py-0.5 text-[11px] font-medium whitespace-nowrap',
                categoryStyles[category ?? 'new'],
            )}
        >
            {status}
        </span>
    );
}

type Transition = {
    id: string;
    name: string;
    to: string;
    to_category: StatusCategory | null;
};

/**
 * The issue's status; opening it lists the statuses its Jira workflow allows next.
 */
function StatusMenu({
    issue,
    loadTransitions,
    onMove,
}: {
    issue: LinkedIssue;
    loadTransitions: () => Promise<{ transitions: Transition[] } | null>;
    onMove: (transitionId: string) => Promise<void>;
}) {
    const { t } = useTranslation();
    const [transitions, setTransitions] = useState<Transition[] | null>(null);

    return (
        <DropdownMenu
            onOpenChange={async (open) => {
                if (open) {
                    setTransitions(null);
                    setTransitions(
                        (await loadTransitions())?.transitions ?? [],
                    );
                }
            }}
        >
            <DropdownMenuTrigger
                className="inline-flex items-center gap-0.5 rounded focus-visible:ring-2 focus-visible:ring-ring/50 focus-visible:outline-none"
                title={t('Change the status in Jira')}
            >
                <StatusBadge
                    status={issue.status}
                    category={issue.status_category}
                />
                <ChevronDown className="size-3" />
            </DropdownMenuTrigger>
            <DropdownMenuContent align="start" className="min-w-48">
                <DropdownMenuLabel className="text-xs text-muted-foreground">
                    {t('Move in Jira to')}
                </DropdownMenuLabel>
                {transitions === null ? (
                    <div className="mx-2 my-1.5 h-5 animate-pulse rounded bg-muted" />
                ) : transitions.length === 0 ? (
                    <p className="px-2 py-1.5 text-xs text-muted-foreground">
                        {t('No moves are allowed from this status.')}
                    </p>
                ) : (
                    transitions.map((transition) => (
                        <DropdownMenuItem
                            key={transition.id}
                            onSelect={() => void onMove(transition.id)}
                        >
                            <StatusBadge
                                status={transition.to}
                                category={transition.to_category}
                            />
                            {transition.name !== transition.to && (
                                <span className="text-xs text-muted-foreground">
                                    {transition.name}
                                </span>
                            )}
                        </DropdownMenuItem>
                    ))
                )}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

/**
 * Jira issues linked to the ticket, shown on the ticket page: link an existing issue or
 * create one from the ticket. Every answer from the server replaces what is shown.
 */
export function JiraPanel({ ticket }: TicketPanelProps) {
    const { t } = useTranslation();
    const { can } = usePermissions();
    const http = useHttp<Body, unknown>({});
    // `useHttp` sends its form data; each request sets what the next one carries.
    const body = useRef<Body>({});
    const [data, setData] = useState<PanelData | null>(null);
    const [failed, setFailed] = useState(false);
    const [mode, setMode] = useState<'link' | 'create' | null>(null);
    const [query, setQuery] = useState('');
    const [results, setResults] = useState<SearchResult[]>([]);
    const [projects, setProjects] = useState<{ key: string; name: string }[]>(
        [],
    );
    const [types, setTypes] = useState<{ id: string; name: string }[]>([]);
    const [project, setProject] = useState('');
    const [issueType, setIssueType] = useState('');
    const [summary, setSummary] = useState(ticket.subject ?? '');

    const request = useCallback(
        async <T,>(
            method: 'get' | 'post' | 'delete',
            url: string,
            payload: Body = {},
        ): Promise<T | null> => {
            body.current = payload;
            let reported = false;

            try {
                return (await http[method](url, {
                    // Jira's refusal (or a bad key) comes back as a validation error.
                    onError: (errors: Record<string, string>) => {
                        reported = true;
                        toast.error(
                            errors.jira ??
                                errors.issue ??
                                Object.values(errors)[0] ??
                                t('Something went wrong.'),
                        );
                    },
                })) as T;
            } catch {
                // This panel talks to the server with `useHttp`, which the app's error toasts
                // (for page visits) don't hear about, so it reports failures itself.
                if (!reported) {
                    toast.error(t('Something went wrong.'));
                }

                return null;
            }
        },
        // eslint-disable-next-line react-hooks/exhaustive-deps
        [],
    );

    const update = useCallback(
        async (
            method: 'get' | 'post' | 'delete',
            url: string,
            payload?: Body,
        ) => {
            const answer = await request<PanelData>(method, url, payload);

            if (answer) {
                setData(answer);
                setFailed(false);
            } else if (method === 'get') {
                setFailed(true);
            }

            return answer;
        },
        [request],
    );

    useEffect(() => {
        http.transform(() => body.current);
        void update('get', index.url(ticket.id));
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [ticket.id, update]);

    // Search as the agent types a key, a link or words.
    useEffect(() => {
        if (mode !== 'link' || query.trim().length < 2) {
            setResults([]);

            return;
        }

        const timeout = window.setTimeout(async () => {
            const answer = await request<{ issues: SearchResult[] }>(
                'get',
                searchRoute.url(ticket.id, { query: { q: query } }),
            );
            setResults(answer?.issues ?? []);
        }, 300);

        return () => window.clearTimeout(timeout);
    }, [mode, query, request, ticket.id]);

    const loadTypes = async (key: string) => {
        setProject(key);
        setTypes([]);

        const answer = await request<{
            issue_types: { id: string; name: string }[];
        }>('get', issueTypesRoute.url(ticket.id, { query: { project: key } }));
        const loaded = answer?.issue_types ?? [];
        setTypes(loaded);
        setIssueType(
            loaded.find((type) => type.id === data?.defaults.issue_type)?.id ??
                loaded[0]?.id ??
                '',
        );
    };

    const openCreate = async () => {
        setMode('create');

        const answer = await request<{
            projects: { key: string; name: string }[];
        }>('get', projectsRoute.url(ticket.id));
        const loaded = answer?.projects ?? [];
        setProjects(loaded);

        const preferred =
            loaded.find((item) => item.key === data?.defaults.project)?.key ??
            loaded[0]?.key;

        if (preferred) {
            await loadTypes(preferred);
        }
    };

    const linkIssue = async (issue: string) => {
        if (await update('post', link.url(ticket.id), { issue })) {
            setMode(null);
            setQuery('');
            setResults([]);
        }
    };

    const canManage = Boolean(data?.can_manage && data.connected);

    return (
        <section className="space-y-3">
            <div className="flex items-center justify-between">
                <h3 className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                    Jira
                </h3>
                {canManage && (
                    <div className="flex gap-1">
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={() => {
                                setMode(mode === 'link' ? null : 'link');
                            }}
                        >
                            <Link2 /> {t('Link')}
                        </Button>
                        <Button
                            variant="ghost"
                            size="icon"
                            title={t('Create Jira issue')}
                            onClick={() =>
                                mode === 'create'
                                    ? setMode(null)
                                    : void openCreate()
                            }
                        >
                            <Plus />
                        </Button>
                    </div>
                )}
            </div>

            {failed && (
                <p className="text-xs text-destructive">
                    {t('Could not load the Jira issues.')}
                </p>
            )}

            {data === null && !failed && (
                <div className="h-8 animate-pulse rounded-md bg-muted" />
            )}

            {data && !data.connected && (
                <p className="text-xs text-muted-foreground">
                    {t('Jira is not connected yet.')}{' '}
                    {can('admin.integrations') && (
                        <Link
                            href={jiraSettings()}
                            className="text-primary hover:underline"
                        >
                            {t('Connect Jira')}
                        </Link>
                    )}
                </p>
            )}

            {data?.error && (
                <p className="text-xs text-destructive">{data.error}</p>
            )}

            {data?.connected && data.issues.length === 0 && mode === null && (
                <p className="text-xs text-muted-foreground">
                    {t('No Jira issues linked.')}
                </p>
            )}

            <ul className="space-y-2">
                {data?.issues.map((issue) => (
                    <li
                        key={issue.id}
                        className="flex items-start gap-2 text-sm"
                    >
                        <div className="min-w-0 flex-1">
                            <a
                                href={issue.url}
                                target="_blank"
                                rel="noreferrer"
                                className={cn(
                                    'group flex items-center gap-1.5 hover:text-primary',
                                    issue.deleted && 'line-through opacity-60',
                                )}
                            >
                                <span className="shrink-0 text-xs font-medium text-muted-foreground">
                                    {issue.key}
                                </span>
                                <span className="truncate">
                                    {issue.summary}
                                </span>
                                <ExternalLink className="size-3 shrink-0 opacity-0 group-hover:opacity-60" />
                            </a>
                            <div className="mt-1 flex flex-wrap items-center gap-1.5 text-xs text-muted-foreground">
                                {issue.deleted ? (
                                    <span>{t('Deleted in Jira')}</span>
                                ) : data.can_manage ? (
                                    <StatusMenu
                                        issue={issue}
                                        loadTransitions={() =>
                                            request<{
                                                transitions: Transition[];
                                            }>(
                                                'get',
                                                transitionsRoute.url({
                                                    ticket: ticket.id,
                                                    jiraIssueLink: issue.id,
                                                }),
                                            )
                                        }
                                        onMove={async (transitionId) => {
                                            const answer = await update(
                                                'post',
                                                transitionRoute.url({
                                                    ticket: ticket.id,
                                                    jiraIssueLink: issue.id,
                                                }),
                                                { transition: transitionId },
                                            );

                                            // The move adds a note, and the status rules may move the ticket.
                                            if (answer) {
                                                router.reload({
                                                    only: [
                                                        'ticket',
                                                        'messages',
                                                        'activity',
                                                    ],
                                                });
                                            }
                                        }}
                                    />
                                ) : (
                                    <StatusBadge
                                        status={issue.status}
                                        category={issue.status_category}
                                    />
                                )}
                                {issue.assignee && (
                                    <span className="truncate">
                                        {issue.assignee}
                                    </span>
                                )}
                            </div>
                        </div>
                        {data.can_manage && (
                            <Button
                                variant="ghost"
                                size="icon"
                                title={t('Unlink')}
                                onClick={() =>
                                    void update(
                                        'delete',
                                        destroy.url({
                                            ticket: ticket.id,
                                            jiraIssueLink: issue.id,
                                        }),
                                    )
                                }
                            >
                                <X />
                            </Button>
                        )}
                    </li>
                ))}
            </ul>

            {mode === 'link' && (
                <form
                    className="space-y-1"
                    onSubmit={(event) => {
                        event.preventDefault();
                        void linkIssue(query);
                    }}
                >
                    <Input
                        autoFocus
                        value={query}
                        onChange={(event) => setQuery(event.target.value)}
                        placeholder={t('Issue key, Jira link or words')}
                    />
                    {results.length > 0 && (
                        <ul className="rounded-lg border bg-card text-sm shadow-xs">
                            {results
                                .filter(
                                    (result) =>
                                        !data?.issues.some(
                                            (issue) => issue.key === result.key,
                                        ),
                                )
                                .map((result) => (
                                    <li key={result.key}>
                                        <button
                                            type="button"
                                            className="flex w-full flex-col gap-1 px-3 py-2 text-left hover:bg-accent"
                                            onClick={() =>
                                                void linkIssue(result.key)
                                            }
                                        >
                                            <span className="truncate">
                                                <span className="mr-1.5 text-xs text-muted-foreground">
                                                    {result.key}
                                                </span>
                                                {result.summary}
                                            </span>
                                            <StatusBadge
                                                status={result.status}
                                                category={
                                                    result.status_category
                                                }
                                            />
                                        </button>
                                    </li>
                                ))}
                        </ul>
                    )}
                </form>
            )}

            {mode === 'create' && (
                <form
                    className="space-y-2"
                    onSubmit={async (event) => {
                        event.preventDefault();

                        if (
                            await update('post', store.url(ticket.id), {
                                project,
                                issue_type: issueType,
                                summary,
                            })
                        ) {
                            setMode(null);
                        }
                    }}
                >
                    <Select
                        value={project}
                        onValueChange={(key) => void loadTypes(key)}
                    >
                        <SelectTrigger
                            className="h-9 w-full"
                            aria-label={t('Project')}
                        >
                            <SelectValue placeholder={t('Project')} />
                        </SelectTrigger>
                        <SelectContent>
                            {projects.map((item) => (
                                <SelectItem key={item.key} value={item.key}>
                                    {item.name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <Select value={issueType} onValueChange={setIssueType}>
                        <SelectTrigger
                            className="h-9 w-full"
                            aria-label={t('Issue type')}
                        >
                            <SelectValue placeholder={t('Issue type')} />
                        </SelectTrigger>
                        <SelectContent>
                            {types.map((type) => (
                                <SelectItem key={type.id} value={type.id}>
                                    {type.name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <Input
                        value={summary}
                        onChange={(event) => setSummary(event.target.value)}
                        aria-label={t('Summary')}
                    />
                    <Button
                        type="submit"
                        size="sm"
                        disabled={
                            http.processing ||
                            project === '' ||
                            issueType === '' ||
                            summary.trim() === ''
                        }
                    >
                        {t('Create issue')}
                    </Button>
                </form>
            )}
        </section>
    );
}
