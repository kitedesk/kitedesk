import { Link } from '@inertiajs/react';
import { MessageSquareText, Search } from 'lucide-react';
import { useMemo, useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Skeleton } from '@/components/ui/skeleton';
import { useTranslation } from '@/hooks/use-translation';
import { index as cannedResponsesIndex } from '@/routes/agent/canned-responses';

export type CannedResponseOption = {
    id: number;
    title: string;
    body: string;
};

/**
 * Values for `{{placeholders}}` in canned responses, e.g. `{ 'requester.name': 'Ana' }`.
 */
export type PlaceholderValues = Record<string, string>;

function escapeHtml(text: string): string {
    return text
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#39;');
}

/**
 * Replace `{{placeholders}}` with HTML-escaped values; unknown placeholders are left as typed.
 */
export function fillPlaceholders(
    body: string,
    values: PlaceholderValues,
): string {
    return body.replace(/\{\{\s*([\w.]+)\s*\}\}/g, (match, key: string) =>
        key in values ? escapeHtml(values[key]) : match,
    );
}

/**
 * Searchable list of canned responses; picking one inserts it into the reply.
 */
export function CannedResponsePicker({
    responses,
    values,
    onInsert,
}: {
    /** Undefined while the list is still loading. */
    responses: CannedResponseOption[] | undefined;
    values: PlaceholderValues;
    onInsert: (html: string) => void;
}) {
    const { t } = useTranslation();
    const [open, setOpen] = useState(false);
    const [query, setQuery] = useState('');

    const matches = useMemo(() => {
        const term = query.trim().toLowerCase();

        return (responses ?? []).filter(
            (response) =>
                term === '' ||
                response.title.toLowerCase().includes(term) ||
                response.body.toLowerCase().includes(term),
        );
    }, [responses, query]);

    const insert = (response: CannedResponseOption) => {
        onInsert(fillPlaceholders(response.body, values));
        setOpen(false);
        setQuery('');
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button type="button" variant="ghost" size="sm">
                    <MessageSquareText /> {t('Responses')}
                </Button>
            </DialogTrigger>
            <DialogContent className="gap-3 sm:max-w-lg">
                <DialogTitle>{t('Insert a canned response')}</DialogTitle>
                <DialogDescription>
                    {t(
                        'Placeholders like {{requester.first_name}} are filled in for this ticket.',
                    )}
                </DialogDescription>
                <div className="relative">
                    <Search className="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground" />
                    <Input
                        value={query}
                        onChange={(event) => setQuery(event.target.value)}
                        placeholder={t('Search responses…')}
                        className="pl-8"
                        autoFocus
                    />
                </div>
                <div className="max-h-80 space-y-1 overflow-y-auto">
                    {responses === undefined ? (
                        <>
                            <Skeleton className="h-12 w-full" />
                            <Skeleton className="h-12 w-full" />
                        </>
                    ) : matches.length === 0 ? (
                        <p className="py-6 text-center text-sm text-muted-foreground">
                            {responses.length === 0
                                ? t('No canned responses yet.')
                                : t('No responses match your search.')}
                        </p>
                    ) : (
                        matches.map((response) => (
                            <button
                                key={response.id}
                                type="button"
                                onClick={() => insert(response)}
                                className="w-full rounded-md px-3 py-2 text-left hover:bg-accent"
                            >
                                <span className="block text-sm font-medium">
                                    {response.title}
                                </span>
                                <span className="line-clamp-1 text-xs text-muted-foreground">
                                    {new DOMParser().parseFromString(
                                        response.body,
                                        'text/html',
                                    ).body.textContent ?? ''}
                                </span>
                            </button>
                        ))
                    )}
                </div>
                <Link
                    href={cannedResponsesIndex()}
                    className="text-xs text-primary hover:underline"
                >
                    {t('Manage canned responses')}
                </Link>
            </DialogContent>
        </Dialog>
    );
}
