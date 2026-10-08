import { Head, Link, router } from '@inertiajs/react';
import {
    ArrowDown,
    ArrowUp,
    BookOpen,
    Eye,
    FilePlus2,
    FileText,
    FolderPlus,
    Pencil,
    Plus,
    ThumbsDown,
    ThumbsUp,
    Trash2,
} from 'lucide-react';
import Heading from '@/components/heading';
import { NameDialog } from '@/components/knowledge-base/name-dialog';
import { Button } from '@/components/ui/button';
import { useTranslation } from '@/hooks/use-translation';
import { relativeTime } from '@/lib/tickets';
import { cn } from '@/lib/utils';
import { index } from '@/routes/admin/knowledge-base';
import {
    create as createArticle,
    edit as editArticle,
} from '@/routes/admin/knowledge-base/articles';
import {
    destroy as destroyCategory,
    move as moveCategory,
    store as storeCategory,
    update as updateCategory,
} from '@/routes/admin/knowledge-base/categories';
import {
    destroy as destroySection,
    move as moveSection,
    store as storeSection,
    update as updateSection,
} from '@/routes/admin/knowledge-base/sections';
import type {
    KbArticleSummary,
    KbCategory,
    KbSection,
} from '@/types/knowledge-base';

const confirmDelete = (message: string, url: string) => {
    if (window.confirm(message)) {
        router.delete(url, { preserveScroll: true });
    }
};

const move = (url: string, direction: 'up' | 'down') =>
    router.post(url, { direction }, { preserveScroll: true });

export default function KnowledgeBaseIndex({
    categories,
}: {
    categories: KbCategory[];
}) {
    const { t } = useTranslation();

    return (
        <>
            <Head title={t('Help center')} />

            <div className="flex flex-wrap items-start justify-between gap-4">
                <Heading
                    title={t('Help center')}
                    description={t(
                        'Organize articles into categories and sections. Only published articles are visible to customers.',
                    )}
                />
                <div className="flex gap-2">
                    <NameDialog
                        title={t('New category')}
                        description={t(
                            'Categories are the top-level topics of your help center.',
                        )}
                        action={storeCategory.url()}
                        method="post"
                        submitLabel={t('Create category')}
                        trigger={
                            <Button variant="outline">
                                <FolderPlus /> {t('New category')}
                            </Button>
                        }
                    />
                    {categories.some(
                        (category) => category.sections.length > 0,
                    ) && (
                        <Button asChild>
                            <Link href={createArticle()}>
                                <FilePlus2 /> {t('New article')}
                            </Link>
                        </Button>
                    )}
                </div>
            </div>

            {categories.length === 0 ? (
                <div className="flex flex-col items-center gap-2 rounded-xl border border-dashed px-6 py-16 text-center">
                    <div className="rounded-full bg-primary/10 p-3 text-primary">
                        <BookOpen className="size-6" />
                    </div>
                    <p className="font-medium">
                        {t('Your help center is empty')}
                    </p>
                    <p className="max-w-sm text-sm text-muted-foreground">
                        {t(
                            'Start with a category (e.g. “Getting started”), add a section inside it, then write your first article.',
                        )}
                    </p>
                </div>
            ) : (
                <div className="space-y-6">
                    {categories.map((category, categoryIndex) => (
                        <CategoryCard
                            key={category.id}
                            category={category}
                            isFirst={categoryIndex === 0}
                            isLast={categoryIndex === categories.length - 1}
                        />
                    ))}
                </div>
            )}
        </>
    );
}

KnowledgeBaseIndex.layout = {
    breadcrumbs: [
        { title: 'Admin center', href: '/admin' },
        { title: 'Help center', href: index() },
    ],
};

function CategoryCard({
    category,
    isFirst,
    isLast,
}: {
    category: KbCategory;
    isFirst: boolean;
    isLast: boolean;
}) {
    const { t } = useTranslation();

    return (
        <section className="overflow-hidden rounded-xl border bg-card shadow-xs">
            <header className="flex flex-wrap items-center gap-3 border-b bg-muted/40 px-4 py-3">
                <div className="min-w-0 flex-1">
                    <h2 className="truncate font-semibold">{category.name}</h2>
                    <p className="truncate text-xs text-muted-foreground">
                        /help/categories/{category.slug}
                        {category.description && ` · ${category.description}`}
                    </p>
                </div>
                <div className="flex items-center gap-1">
                    <IconButton
                        label={t('Move category up')}
                        disabled={isFirst}
                        onClick={() =>
                            move(moveCategory.url(category.id), 'up')
                        }
                    >
                        <ArrowUp />
                    </IconButton>
                    <IconButton
                        label={t('Move category down')}
                        disabled={isLast}
                        onClick={() =>
                            move(moveCategory.url(category.id), 'down')
                        }
                    >
                        <ArrowDown />
                    </IconButton>
                    <NameDialog
                        title={t('Edit category')}
                        action={updateCategory.url(category.id)}
                        method="patch"
                        initial={{
                            name: category.name,
                            slug: category.slug,
                            description: category.description ?? '',
                        }}
                        trigger={
                            <IconButton label={t('Edit category')}>
                                <Pencil />
                            </IconButton>
                        }
                    />
                    <IconButton
                        label={t('Delete category')}
                        destructive
                        onClick={() =>
                            confirmDelete(
                                t(
                                    'Delete “:name” and all of its sections and articles?',
                                    { name: category.name },
                                ),
                                destroyCategory.url(category.id),
                            )
                        }
                    >
                        <Trash2 />
                    </IconButton>
                    <NameDialog
                        title={t('New section in :category', {
                            category: category.name,
                        })}
                        action={storeSection.url()}
                        method="post"
                        extra={{ category_id: category.id }}
                        submitLabel={t('Create section')}
                        trigger={
                            <Button
                                variant="outline"
                                size="sm"
                                className="ml-1"
                            >
                                <Plus /> {t('Section')}
                            </Button>
                        }
                    />
                </div>
            </header>

            {category.sections.length === 0 ? (
                <p className="px-4 py-6 text-center text-sm text-muted-foreground">
                    {t('No sections yet — add one to start writing articles.')}
                </p>
            ) : (
                <div className="divide-y">
                    {category.sections.map((section, sectionIndex) => (
                        <SectionBlock
                            key={section.id}
                            section={section}
                            isFirst={sectionIndex === 0}
                            isLast={
                                sectionIndex === category.sections.length - 1
                            }
                        />
                    ))}
                </div>
            )}
        </section>
    );
}

function SectionBlock({
    section,
    isFirst,
    isLast,
}: {
    section: KbSection;
    isFirst: boolean;
    isLast: boolean;
}) {
    const { t, tChoice } = useTranslation();

    return (
        <div className="px-4 py-3">
            <div className="flex flex-wrap items-center gap-2">
                <h3 className="min-w-0 flex-1 truncate text-sm font-medium">
                    {section.name}
                    <span className="ml-2 text-xs font-normal text-muted-foreground">
                        {tChoice(
                            ':count article|:count articles',
                            section.articles.length,
                        )}
                    </span>
                </h3>
                <div className="flex items-center gap-1">
                    <IconButton
                        label={t('Move section up')}
                        disabled={isFirst}
                        onClick={() => move(moveSection.url(section.id), 'up')}
                    >
                        <ArrowUp />
                    </IconButton>
                    <IconButton
                        label={t('Move section down')}
                        disabled={isLast}
                        onClick={() =>
                            move(moveSection.url(section.id), 'down')
                        }
                    >
                        <ArrowDown />
                    </IconButton>
                    <NameDialog
                        title={t('Edit section')}
                        action={updateSection.url(section.id)}
                        method="patch"
                        initial={{
                            name: section.name,
                            slug: section.slug,
                            description: section.description ?? '',
                        }}
                        trigger={
                            <IconButton label={t('Edit section')}>
                                <Pencil />
                            </IconButton>
                        }
                    />
                    <IconButton
                        label={t('Delete section')}
                        destructive
                        onClick={() =>
                            confirmDelete(
                                t(
                                    'Delete the section “:name” and its articles?',
                                    { name: section.name },
                                ),
                                destroySection.url(section.id),
                            )
                        }
                    >
                        <Trash2 />
                    </IconButton>
                    <Button asChild variant="ghost" size="sm">
                        <Link
                            href={createArticle({
                                query: { section_id: section.id },
                            })}
                        >
                            <Plus /> {t('Article')}
                        </Link>
                    </Button>
                </div>
            </div>

            {section.articles.length > 0 && (
                <ul className="mt-2 space-y-0.5">
                    {section.articles.map((article) => (
                        <ArticleRow key={article.id} article={article} />
                    ))}
                </ul>
            )}
        </div>
    );
}

function ArticleRow({ article }: { article: KbArticleSummary }) {
    const { t } = useTranslation();

    return (
        <li>
            <Link
                href={editArticle(article.id)}
                className="group flex items-center gap-3 rounded-md px-2 py-1.5 text-sm transition-colors hover:bg-muted/60"
            >
                <FileText className="size-4 shrink-0 text-muted-foreground" />
                <span className="min-w-0 flex-1 truncate group-hover:text-primary">
                    {article.title}
                </span>
                <span
                    className={cn(
                        'rounded px-1.5 py-0.5 text-[11px] font-medium',
                        article.is_published
                            ? 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300'
                            : 'bg-muted text-muted-foreground',
                    )}
                >
                    {article.is_published ? t('Published') : t('Draft')}
                </span>
                <span className="hidden items-center gap-3 text-xs text-muted-foreground tabular-nums sm:flex">
                    <span
                        className="inline-flex items-center gap-1"
                        title={t('Views')}
                    >
                        <Eye className="size-3" /> {article.view_count}
                    </span>
                    <span
                        className="inline-flex items-center gap-1"
                        title={t('Helpful votes')}
                    >
                        <ThumbsUp className="size-3" /> {article.helpful_count}
                    </span>
                    <span
                        className="inline-flex items-center gap-1"
                        title={t('Not helpful votes')}
                    >
                        <ThumbsDown className="size-3" />{' '}
                        {article.not_helpful_count}
                    </span>
                    <span className="w-20 text-right">
                        {relativeTime(article.updated_at)}
                    </span>
                </span>
            </Link>
        </li>
    );
}

function IconButton({
    label,
    children,
    onClick,
    disabled,
    destructive,
    ...props
}: {
    label: string;
    children: React.ReactNode;
    onClick?: () => void;
    disabled?: boolean;
    destructive?: boolean;
} & Omit<React.ComponentProps<typeof Button>, 'onClick' | 'children'>) {
    return (
        <Button
            type="button"
            variant="ghost"
            size="icon"
            aria-label={label}
            title={label}
            disabled={disabled}
            onClick={onClick}
            className={cn(
                'size-7 text-muted-foreground [&_svg]:size-3.5',
                destructive && 'hover:text-destructive',
            )}
            {...props}
        >
            {children}
        </Button>
    );
}
