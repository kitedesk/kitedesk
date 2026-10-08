import { Head, Link } from '@inertiajs/react';
import { FileText } from 'lucide-react';
import { HelpSearch } from '@/components/help/help-search';
import { useTranslation } from '@/hooks/use-translation';
import { show } from '@/routes/help/articles';
import { ContactCta } from './index';

type Props = {
    query: string;
    articles: {
        id: number;
        title: string;
        slug: string;
        excerpt: string | null;
    }[];
};

export default function HelpSearchResults({ query, articles }: Props) {
    const { t, tChoice } = useTranslation();

    return (
        <>
            <Head
                title={query ? t('Search: :query', { query }) : t('Search')}
            />

            <div className="mx-auto max-w-3xl space-y-8 px-4 py-10">
                <HelpSearch initialQuery={query} size="md" />

                <p className="text-sm text-muted-foreground">
                    {tChoice(
                        ':count result for “:query”|:count results for “:query”',
                        articles.length,
                        { query },
                    )}
                </p>

                <ul className="space-y-2">
                    {articles.map((article) => (
                        <li key={article.id}>
                            <Link
                                href={show(article.slug)}
                                className="flex gap-3 rounded-xl border bg-card p-4 transition-colors hover:border-primary/40"
                            >
                                <FileText className="mt-0.5 size-4 shrink-0 text-muted-foreground" />
                                <span>
                                    <span className="block font-medium">
                                        {article.title}
                                    </span>
                                    {article.excerpt && (
                                        <span className="text-sm text-muted-foreground">
                                            {article.excerpt}
                                        </span>
                                    )}
                                </span>
                            </Link>
                        </li>
                    ))}
                </ul>

                <ContactCta />
            </div>
        </>
    );
}
