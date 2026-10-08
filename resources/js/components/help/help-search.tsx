import { Link, router } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { useEffect, useState } from 'react';
import { useTranslation } from '@/hooks/use-translation';
import { cn } from '@/lib/utils';
import { search } from '@/routes/help';
import { show } from '@/routes/help/articles';

type Result = {
    id: number;
    title: string;
    slug: string;
    excerpt: string | null;
};

/**
 * Help center search with instant suggestions; Enter opens the full results page.
 */
export function HelpSearch({
    initialQuery = '',
    size = 'lg',
    className,
}: {
    initialQuery?: string;
    size?: 'lg' | 'md';
    className?: string;
}) {
    const { t } = useTranslation();
    const [query, setQuery] = useState(initialQuery);
    const [results, setResults] = useState<Result[]>([]);
    const [focused, setFocused] = useState(false);

    useEffect(() => {
        if (query.trim().length < 2) {
            setResults([]);

            return;
        }

        const controller = new AbortController();

        const timeout = window.setTimeout(async () => {
            try {
                const response = await fetch(
                    search.url({ query: { q: query } }),
                    {
                        headers: { Accept: 'application/json' },
                        signal: controller.signal,
                    },
                );

                if (response.ok) {
                    setResults(
                        (
                            (await response.json()) as { articles: Result[] }
                        ).articles.slice(0, 6),
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
    }, [query]);

    return (
        <form
            role="search"
            onSubmit={(event) => {
                event.preventDefault();
                router.get(search.url(), { q: query });
            }}
            className={cn('relative w-full', className)}
        >
            <Search
                className={cn(
                    'pointer-events-none absolute top-1/2 left-4 -translate-y-1/2 text-muted-foreground',
                    size === 'lg' ? 'size-5' : 'size-4',
                )}
            />
            <input
                type="search"
                value={query}
                onChange={(event) => setQuery(event.target.value)}
                onFocus={() => setFocused(true)}
                onBlur={() => window.setTimeout(() => setFocused(false), 150)}
                placeholder={t('Search for answers…')}
                aria-label={t('Search the help center')}
                className={cn(
                    'w-full rounded-2xl border bg-background shadow-sm transition-shadow outline-none focus:shadow-lg focus:ring-4 focus:ring-primary/15',
                    size === 'lg'
                        ? 'h-14 pr-4 pl-12 text-base'
                        : 'h-11 pr-3 pl-10 text-sm',
                )}
            />
            {focused && results.length > 0 && (
                <ul className="absolute z-20 mt-2 w-full overflow-hidden rounded-xl border bg-popover text-left shadow-xl">
                    {results.map((result) => (
                        <li key={result.id}>
                            <Link
                                href={show(result.slug)}
                                className="block px-4 py-3 hover:bg-accent"
                            >
                                <p className="text-sm font-medium">
                                    {result.title}
                                </p>
                                {result.excerpt && (
                                    <p className="line-clamp-1 text-xs text-muted-foreground">
                                        {result.excerpt}
                                    </p>
                                )}
                            </Link>
                        </li>
                    ))}
                </ul>
            )}
        </form>
    );
}
