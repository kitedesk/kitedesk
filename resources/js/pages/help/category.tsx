import { Head, Link } from '@inertiajs/react';
import { ChevronRight, FileText } from 'lucide-react';
import { HelpSearch } from '@/components/help/help-search';
import { useTranslation } from '@/hooks/use-translation';
import { index } from '@/routes/help';
import { show as showArticle } from '@/routes/help/articles';
import { ContactCta } from './index';

type Props = {
    category: {
        id: number;
        name: string;
        slug: string;
        description: string | null;
    };
    sections: {
        id: number;
        name: string;
        description: string | null;
        articles: {
            id: number;
            title: string;
            slug: string;
            excerpt: string | null;
        }[];
    }[];
};

export default function HelpCategory({ category, sections }: Props) {
    const { t } = useTranslation();

    return (
        <>
            <Head title={category.name} />

            <div className="mx-auto max-w-4xl space-y-10 px-4 py-10">
                <div className="space-y-4">
                    <nav className="flex items-center gap-1 text-sm text-muted-foreground">
                        <Link href={index()} className="hover:text-foreground">
                            {t('Help center')}
                        </Link>
                        <ChevronRight className="size-3.5" />
                        <span className="text-foreground">{category.name}</span>
                    </nav>
                    <h1 className="text-3xl font-semibold tracking-tight">
                        {category.name}
                    </h1>
                    {category.description && (
                        <p className="text-muted-foreground">
                            {category.description}
                        </p>
                    )}
                    <HelpSearch size="md" />
                </div>

                {sections.map((section) => (
                    <section key={section.id} className="space-y-3">
                        <div>
                            <h2 className="text-lg font-semibold">
                                {section.name}
                            </h2>
                            {section.description && (
                                <p className="text-sm text-muted-foreground">
                                    {section.description}
                                </p>
                            )}
                        </div>
                        <ul className="divide-y overflow-hidden rounded-xl border bg-card">
                            {section.articles.map((article) => (
                                <li key={article.id}>
                                    <Link
                                        href={showArticle(article.slug)}
                                        className="flex items-start gap-3 px-4 py-3.5 transition-colors hover:bg-muted/50"
                                    >
                                        <FileText className="mt-0.5 size-4 shrink-0 text-muted-foreground" />
                                        <span>
                                            <span className="block text-sm font-medium">
                                                {article.title}
                                            </span>
                                            {article.excerpt && (
                                                <span className="line-clamp-1 text-xs text-muted-foreground">
                                                    {article.excerpt}
                                                </span>
                                            )}
                                        </span>
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    </section>
                ))}

                <ContactCta />
            </div>
        </>
    );
}
