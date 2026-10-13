import { Head, Link, router } from '@inertiajs/react';
import { ChevronLeft, ChevronRight, Download, ScrollText } from 'lucide-react';
import { useState } from 'react';
import { AdminPageHeader } from '@/components/admin/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { useTranslation } from '@/hooks/use-translation';
import { formatDateTime, relativeTime } from '@/lib/tickets';
import { exportMethod, index } from '@/routes/admin/audit-log';

type Entry = {
    id: number;
    created_at: string;
    category: string | null;
    event: string | null;
    action: string;
    actor: {
        name: string;
        user_id: number | null;
        workflow: string | null;
        support: string | null;
    };
    target: { type: string | null; name: string | null; url: string | null };
    ip: string | null;
    user_agent: string | null;
    changes: { field: string; old: unknown; new: unknown }[];
    details: Record<string, unknown>;
};

type Filters = {
    category: string | null;
    event: string | null;
    user: number | null;
    from: string | null;
    to: string | null;
};

type Props = {
    entries: {
        data: Entry[];
        next_page_url: string | null;
        prev_page_url: string | null;
    };
    filters: Filters;
    categories: Record<string, string>;
    actions: Record<string, string>;
    people: { id: number; name: string }[];
    selectedUser: { id: number; name: string } | null;
    retentionDays: number;
};

const ALL = 'all';

export default function AuditLog({
    entries,
    filters,
    categories,
    actions,
    people,
    selectedUser,
    retentionDays,
}: Props) {
    const { t } = useTranslation();
    const [selected, setSelected] = useState<Entry | null>(null);

    const query = (next: Partial<Filters>) =>
        Object.fromEntries(
            Object.entries({ ...filters, ...next }).filter(
                ([, value]) => value !== null && value !== '',
            ),
        ) as Record<string, string | number>;

    const filter = (next: Partial<Filters>) =>
        router.get(index.url(), query(next), {
            preserveState: true,
            preserveScroll: true,
        });

    const peopleOptions =
        selectedUser && !people.some((person) => person.id === selectedUser.id)
            ? [selectedUser, ...people]
            : people;

    return (
        <>
            <Head title={t('Audit log')} />
            <AdminPageHeader
                title={t('Audit log')}
                description={t(
                    'Who signed in, and who changed users, roles, settings and tickets. Entries are kept for :days days.',
                    { days: retentionDays },
                )}
                actions={
                    <Button variant="outline" asChild>
                        <a href={exportMethod.url({ query: query({}) })}>
                            <Download /> {t('Export CSV')}
                        </a>
                    </Button>
                }
            />

            <div className="mb-4 flex flex-wrap items-end gap-3">
                <FilterSelect
                    label={t('Category')}
                    value={filters.category}
                    options={categories}
                    onChange={(category) => filter({ category })}
                />
                <FilterSelect
                    label={t('Action')}
                    value={filters.event}
                    options={actions}
                    onChange={(event) => filter({ event })}
                />
                <FilterSelect
                    label={t('Person')}
                    value={filters.user !== null ? String(filters.user) : null}
                    options={Object.fromEntries(
                        peopleOptions.map((person) => [
                            String(person.id),
                            person.name,
                        ]),
                    )}
                    onChange={(user) =>
                        filter({ user: user !== null ? Number(user) : null })
                    }
                />
                <div className="grid gap-1">
                    <span className="text-xs text-muted-foreground">
                        {t('From')}
                    </span>
                    <Input
                        type="date"
                        className="w-40"
                        value={filters.from ?? ''}
                        onChange={(event) =>
                            filter({ from: event.target.value || null })
                        }
                    />
                </div>
                <div className="grid gap-1">
                    <span className="text-xs text-muted-foreground">
                        {t('To')}
                    </span>
                    <Input
                        type="date"
                        className="w-40"
                        value={filters.to ?? ''}
                        onChange={(event) =>
                            filter({ to: event.target.value || null })
                        }
                    />
                </div>
            </div>

            {entries.data.length === 0 ? (
                <div className="flex flex-col items-center gap-2 rounded-xl border border-dashed px-4 py-12 text-center text-sm text-muted-foreground">
                    <ScrollText className="size-6" />
                    {t('Nothing recorded matches these filters.')}
                </div>
            ) : (
                <div className="overflow-x-auto rounded-xl border bg-card shadow-xs">
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="border-b bg-muted/40 text-left text-xs text-muted-foreground">
                                <th className="px-4 py-2.5 font-medium">
                                    {t('When')}
                                </th>
                                <th className="px-3 py-2.5 font-medium">
                                    {t('Person')}
                                </th>
                                <th className="px-3 py-2.5 font-medium">
                                    {t('Action')}
                                </th>
                                <th className="hidden px-3 py-2.5 font-medium md:table-cell">
                                    {t('Target')}
                                </th>
                                <th className="hidden px-4 py-2.5 font-medium lg:table-cell">
                                    {t('IP address')}
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {entries.data.map((entry) => (
                                <tr
                                    key={entry.id}
                                    onClick={() => setSelected(entry)}
                                    className="cursor-pointer border-b last:border-0 hover:bg-muted/40"
                                >
                                    <td
                                        className="px-4 py-3 whitespace-nowrap text-muted-foreground"
                                        title={formatDateTime(entry.created_at)}
                                    >
                                        {relativeTime(entry.created_at)}
                                    </td>
                                    <td className="px-3 py-3">
                                        <Actor entry={entry} />
                                    </td>
                                    <td className="px-3 py-3">
                                        {entry.action}
                                        {entry.changes.length > 0 && (
                                            <span className="ml-1 text-xs text-muted-foreground">
                                                (
                                                {entry.changes
                                                    .map(
                                                        (change) =>
                                                            change.field,
                                                    )
                                                    .join(', ')}
                                                )
                                            </span>
                                        )}
                                    </td>
                                    <td className="hidden px-3 py-3 md:table-cell">
                                        <Target entry={entry} />
                                    </td>
                                    <td className="hidden px-4 py-3 font-mono text-xs text-muted-foreground lg:table-cell">
                                        {entry.ip ?? '—'}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}

            {(entries.prev_page_url || entries.next_page_url) && (
                <div className="mt-4 flex justify-center gap-2">
                    <Button
                        variant="outline"
                        size="sm"
                        disabled={!entries.prev_page_url}
                        asChild={!!entries.prev_page_url}
                    >
                        {entries.prev_page_url ? (
                            <Link href={entries.prev_page_url} preserveScroll>
                                <ChevronLeft /> {t('Newer')}
                            </Link>
                        ) : (
                            <span>
                                <ChevronLeft /> {t('Newer')}
                            </span>
                        )}
                    </Button>
                    <Button
                        variant="outline"
                        size="sm"
                        disabled={!entries.next_page_url}
                        asChild={!!entries.next_page_url}
                    >
                        {entries.next_page_url ? (
                            <Link href={entries.next_page_url} preserveScroll>
                                {t('Older')} <ChevronRight />
                            </Link>
                        ) : (
                            <span>
                                {t('Older')} <ChevronRight />
                            </span>
                        )}
                    </Button>
                </div>
            )}

            <Sheet
                open={selected !== null}
                onOpenChange={(open) => !open && setSelected(null)}
            >
                <SheetContent className="w-full overflow-y-auto sm:max-w-lg">
                    {selected && (
                        <EntryDetails
                            entry={selected}
                            categories={categories}
                        />
                    )}
                </SheetContent>
            </Sheet>
        </>
    );
}

function FilterSelect({
    label,
    value,
    options,
    onChange,
}: {
    label: string;
    value: string | null;
    options: Record<string, string>;
    onChange: (value: string | null) => void;
}) {
    const { t } = useTranslation();

    return (
        <div className="grid gap-1">
            <span className="text-xs text-muted-foreground">{label}</span>
            <Select
                value={value ?? ALL}
                onValueChange={(next) => onChange(next === ALL ? null : next)}
            >
                <SelectTrigger className="w-48">
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value={ALL}>{t('All')}</SelectItem>
                    {Object.entries(options).map(([key, optionLabel]) => (
                        <SelectItem key={key} value={key}>
                            {optionLabel}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
        </div>
    );
}

function Actor({ entry }: { entry: Entry }) {
    const { t } = useTranslation();

    return (
        <div className="min-w-0">
            <p className="truncate font-medium">{entry.actor.name}</p>
            {entry.actor.support && (
                <p className="truncate text-xs text-muted-foreground">
                    {t('via KiteDesk support (:name)', {
                        name: entry.actor.support,
                    })}
                </p>
            )}
            {entry.actor.workflow && (
                <p className="truncate text-xs text-muted-foreground">
                    {t('Workflow: :name', { name: entry.actor.workflow })}
                </p>
            )}
        </div>
    );
}

function Target({ entry }: { entry: Entry }) {
    if (!entry.target.type && !entry.target.name) {
        return <span className="text-muted-foreground">—</span>;
    }

    return (
        <span className="flex min-w-0 items-center gap-2">
            {entry.target.type && (
                <Badge variant="secondary" className="shrink-0">
                    {entry.target.type}
                </Badge>
            )}
            {entry.target.url ? (
                <Link
                    href={entry.target.url}
                    onClick={(event) => event.stopPropagation()}
                    className="truncate hover:underline"
                >
                    {entry.target.name}
                </Link>
            ) : (
                <span className="truncate">{entry.target.name}</span>
            )}
        </span>
    );
}

function display(value: unknown): string {
    if (value === null || value === undefined || value === '') {
        return '—';
    }

    return typeof value === 'string' ? value : JSON.stringify(value);
}

function EntryDetails({
    entry,
    categories,
}: {
    entry: Entry;
    categories: Record<string, string>;
}) {
    const { t } = useTranslation();

    const rows: [string, string][] = [
        [t('When'), formatDateTime(entry.created_at)],
        [
            t('Category'),
            entry.category
                ? (categories[entry.category] ?? entry.category)
                : '—',
        ],
        [t('IP address'), entry.ip ?? '—'],
        [t('Browser'), entry.user_agent ?? '—'],
    ];

    return (
        <div className="space-y-6 p-4">
            <SheetHeader className="p-0">
                <SheetTitle>{entry.action}</SheetTitle>
                <SheetDescription>
                    {entry.actor.name}
                    {entry.target.name ? ` · ${entry.target.name}` : ''}
                </SheetDescription>
            </SheetHeader>

            <dl className="space-y-2 text-sm">
                {rows.map(([label, value]) => (
                    <div
                        key={label}
                        className="grid grid-cols-[7rem_1fr] gap-2"
                    >
                        <dt className="text-muted-foreground">{label}</dt>
                        <dd className="break-words">{value}</dd>
                    </div>
                ))}
            </dl>

            {entry.changes.length > 0 && (
                <section className="space-y-2">
                    <h3 className="text-sm font-medium">{t('Changes')}</h3>
                    <ul className="divide-y rounded-lg border text-sm">
                        {entry.changes.map((change) => (
                            <li
                                key={change.field}
                                className="space-y-1 px-3 py-2"
                            >
                                <p className="font-mono text-xs text-muted-foreground">
                                    {change.field}
                                </p>
                                <p className="break-words">
                                    <span className="text-muted-foreground line-through">
                                        {display(change.old)}
                                    </span>{' '}
                                    → <span>{display(change.new)}</span>
                                </p>
                            </li>
                        ))}
                    </ul>
                </section>
            )}

            {Object.keys(entry.details).length > 0 && (
                <section className="space-y-2">
                    <h3 className="text-sm font-medium">{t('Details')}</h3>
                    <dl className="divide-y rounded-lg border text-sm">
                        {Object.entries(entry.details).map(([key, value]) => (
                            <div key={key} className="space-y-1 px-3 py-2">
                                <dt className="font-mono text-xs text-muted-foreground">
                                    {key}
                                </dt>
                                <dd className="break-words">
                                    {display(value)}
                                </dd>
                            </div>
                        ))}
                    </dl>
                </section>
            )}
        </div>
    );
}

AuditLog.layout = {
    breadcrumbs: [
        { title: 'Admin center', href: '/admin' },
        { title: 'Audit log', href: index() },
    ],
};
