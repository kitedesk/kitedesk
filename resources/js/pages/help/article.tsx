import { Head, Link } from '@inertiajs/react';
import { ChevronRight, ThumbsDown, ThumbsUp } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { useTranslation } from '@/hooks/use-translation';
import { formatDateTime } from '@/lib/tickets';
import { index } from '@/routes/help';
import { feedback, show } from '@/routes/help/articles';
import { show as showCategory } from '@/routes/help/categories';
import { ContactCta } from './index';

type Props = {
    article: {
        id: number;
        title: string;
        slug: string;
        excerpt: string | null;
        body: string;
        updated_at: string;
        section: { id: number; name: string };
        category: { id: number; name: string; slug: string };
    };
    related: { id: number; title: string; slug: string }[];
};

export default function HelpArticle({ article, related }: Props) {
    const { t } = useTranslation();
    const [vote, setVote] = useState<'yes' | 'no' | null>(null);

    const sendFeedback = async (helpful: boolean) => {
        setVote(helpful ? 'yes' : 'no');
        const token = decodeURIComponent(
            document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? '',
        );

        await fetch(feedback.url(article.slug), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-XSRF-TOKEN': token,
            },
            body: JSON.stringify({ helpful }),
        });
    };

    return (
        <>
            <Head title={article.title} />

            <div className="mx-auto grid max-w-6xl gap-12 px-4 py-10 lg:grid-cols-[minmax(0,1fr)_16rem]">
                <article className="min-w-0">
                    <nav className="mb-6 flex flex-wrap items-center gap-1 text-sm text-muted-foreground">
                        <Link href={index()} className="hover:text-foreground">
                            {t('Help center')}
                        </Link>
                        <ChevronRight className="size-3.5" />
                        <Link
                            href={showCategory(article.category.slug)}
                            className="hover:text-foreground"
                        >
                            {article.category.name}
                        </Link>
                        <ChevronRight className="size-3.5" />
                        <span>{article.section.name}</span>
                    </nav>

                    <h1 className="text-3xl font-semibold tracking-tight text-balance">
                        {article.title}
                    </h1>
                    <p className="mt-2 text-sm text-muted-foreground">
                        {t('Updated :date', {
                            date: formatDateTime(article.updated_at),
                        })}
                    </p>

                    {/* Article bodies are sanitized with HTML Purifier when saved. */}
                    <div
                        className="prose-ticket mt-8 text-base"
                        dangerouslySetInnerHTML={{ __html: article.body }}
                    />

                    <div className="mt-12 flex flex-col items-center gap-3 rounded-xl border p-6 text-center">
                        {vote ? (
                            <p className="text-sm">
                                {t('Thanks for your feedback!')}
                            </p>
                        ) : (
                            <>
                                <p className="text-sm font-medium">
                                    {t('Was this article helpful?')}
                                </p>
                                <div className="flex gap-2">
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={() => sendFeedback(true)}
                                    >
                                        <ThumbsUp /> {t('Yes')}
                                    </Button>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={() => sendFeedback(false)}
                                    >
                                        <ThumbsDown /> {t('No')}
                                    </Button>
                                </div>
                            </>
                        )}
                    </div>

                    <div className="mt-10">
                        <ContactCta />
                    </div>
                </article>

                {related.length > 0 && (
                    <aside className="lg:pt-14">
                        <h2 className="mb-3 text-xs font-medium tracking-wide text-muted-foreground uppercase">
                            {t('Related articles')}
                        </h2>
                        <ul className="space-y-1">
                            {related.map((item) => (
                                <li key={item.id}>
                                    <Link
                                        href={show(item.slug)}
                                        className="block rounded-md px-2 py-1.5 text-sm hover:bg-accent"
                                    >
                                        {item.title}
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    </aside>
                )}
            </div>
        </>
    );
}
