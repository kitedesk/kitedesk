import { Head, Link, usePage } from '@inertiajs/react';
import { m } from 'framer-motion';
import { ArrowRight, BookOpen, LifeBuoy } from 'lucide-react';
import { HelpSearch } from '@/components/help/help-search';
import { Button } from '@/components/ui/button';
import { useNewRequestUrl } from '@/hooks/use-new-request-url';
import { useTranslation } from '@/hooks/use-translation';
import { show as showArticle } from '@/routes/help/articles';
import { show as showCategory } from '@/routes/help/categories';

type Category = {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    articles_count: number;
};

type ArticleSummary = {
    id: number;
    title: string;
    slug: string;
    excerpt: string | null;
};

export default function HelpCenter({
    categories,
    popularArticles,
}: {
    categories: Category[];
    popularArticles: ArticleSummary[];
}) {
    const { t, tChoice } = useTranslation();
    const { branding } = usePage().props;

    return (
        <>
            <Head title={t('Help center')} />

            <section className="relative overflow-hidden border-b">
                <div
                    aria-hidden
                    className="absolute inset-0 -z-10 bg-[radial-gradient(60%_80%_at_50%_0%,color-mix(in_oklch,var(--primary)_18%,transparent),transparent)]"
                />
                <div className="mx-auto max-w-3xl px-4 py-20 text-center">
                    <m.h1
                        initial={{ opacity: 0, y: 8 }}
                        animate={{ opacity: 1, y: 0 }}
                        className="text-3xl font-semibold tracking-tight text-balance sm:text-4xl"
                    >
                        {branding?.helpTitle ?? t('How can we help?')}
                    </m.h1>
                    <p className="mt-3 text-muted-foreground">
                        {branding?.helpSubtitle ??
                            t('Search our guides or browse by topic.')}
                    </p>
                    <HelpSearch className="mt-8" />
                </div>
            </section>

            <div className="mx-auto max-w-6xl space-y-14 px-4 py-14">
                {categories.length > 0 && (
                    <section>
                        <h2 className="mb-5 text-lg font-semibold">
                            {t('Browse topics')}
                        </h2>
                        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            {categories.map((category, index) => (
                                <m.div
                                    key={category.id}
                                    initial={{ opacity: 0, y: 10 }}
                                    animate={{ opacity: 1, y: 0 }}
                                    transition={{ delay: index * 0.04 }}
                                >
                                    <Link
                                        href={showCategory(category.slug)}
                                        className="group flex h-full flex-col rounded-2xl border bg-card p-5 shadow-xs transition-all hover:-translate-y-0.5 hover:border-primary/40 hover:shadow-md"
                                    >
                                        <span className="mb-4 flex size-10 items-center justify-center rounded-xl bg-primary/10 text-primary">
                                            <BookOpen className="size-5" />
                                        </span>
                                        <span className="font-medium">
                                            {category.name}
                                        </span>
                                        {category.description && (
                                            <span className="mt-1 line-clamp-2 text-sm text-muted-foreground">
                                                {category.description}
                                            </span>
                                        )}
                                        <span className="mt-auto pt-4 text-xs text-muted-foreground">
                                            {tChoice(
                                                ':count article|:count articles',
                                                category.articles_count,
                                            )}
                                        </span>
                                    </Link>
                                </m.div>
                            ))}
                        </div>
                    </section>
                )}

                {popularArticles.length > 0 && (
                    <section>
                        <h2 className="mb-5 text-lg font-semibold">
                            {t('Popular articles')}
                        </h2>
                        <ul className="grid gap-x-8 gap-y-1 md:grid-cols-2">
                            {popularArticles.map((article) => (
                                <li key={article.id}>
                                    <Link
                                        href={showArticle(article.slug)}
                                        className="group flex items-center justify-between gap-4 rounded-lg px-3 py-3 hover:bg-accent"
                                    >
                                        <span className="text-sm font-medium">
                                            {article.title}
                                        </span>
                                        <ArrowRight className="size-4 shrink-0 text-muted-foreground transition-transform group-hover:translate-x-0.5" />
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    </section>
                )}

                <ContactCta />
            </div>
        </>
    );
}

export function ContactCta() {
    const { t } = useTranslation();
    const newRequestUrl = useNewRequestUrl();

    return (
        <section className="flex flex-col items-center gap-4 rounded-2xl border bg-muted/40 px-6 py-10 text-center sm:flex-row sm:text-left">
            <span className="flex size-12 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary">
                <LifeBuoy className="size-6" />
            </span>
            <div className="flex-1">
                <h2 className="font-semibold">
                    {t("Can't find what you're looking for?")}
                </h2>
                <p className="text-sm text-muted-foreground">
                    {t('Our team is here to help — send us a request.')}
                </p>
            </div>
            <Button asChild>
                <Link href={newRequestUrl}>{t('Submit a request')}</Link>
            </Button>
        </section>
    );
}
