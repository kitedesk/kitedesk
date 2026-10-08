import { Head, Link, router, setLayoutProps, useForm } from '@inertiajs/react';
import { ExternalLink, Trash2 } from 'lucide-react';
import { useState } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { RichTextEditor } from '@/components/tickets/rich-text-editor';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectLabel,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useTranslation } from '@/hooks/use-translation';
import { formatDateTime } from '@/lib/tickets';
import { cn } from '@/lib/utils';
import { index } from '@/routes/admin/knowledge-base';
import { destroy, store, update } from '@/routes/admin/knowledge-base/articles';
import { show as publicArticle } from '@/routes/help/articles';
import type { Option } from '@/types';
import type {
    ArticleStatus,
    KbArticle,
    KbSectionOption,
} from '@/types/knowledge-base';

type Props = {
    article: KbArticle | null;
    sectionId: number | null;
    sections: KbSectionOption[];
    statuses: Option<ArticleStatus>[];
};

function slugify(value: string): string {
    return value
        .normalize('NFKD')
        .replace(/[̀-ͯ]/g, '')
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');
}

export default function ArticleForm({
    article,
    sectionId,
    sections,
    statuses,
}: Props) {
    const { t } = useTranslation();
    const isEditing = article !== null;
    const [slugTouched, setSlugTouched] = useState(isEditing);

    const form = useForm({
        title: article?.title ?? '',
        slug: article?.slug ?? '',
        section_id: sectionId ?? sections[0]?.id ?? null,
        excerpt: article?.excerpt ?? '',
        body: article?.body ?? '',
        status: (article?.status ?? 'draft') as ArticleStatus,
    });

    setLayoutProps({
        breadcrumbs: [
            { title: 'Admin center', href: '/admin' },
            { title: 'Help center', href: index() },
            { title: isEditing ? 'Edit article' : 'New article', href: '' },
        ],
    });

    const submit = (event: React.FormEvent) => {
        event.preventDefault();

        if (article) {
            form.patch(update.url(article.id), { preserveScroll: true });
        } else {
            form.post(store.url());
        }
    };

    const groupedSections = sections.reduce<Record<string, KbSectionOption[]>>(
        (groups, section) => {
            (groups[section.category] ??= []).push(section);

            return groups;
        },
        {},
    );

    return (
        <>
            <Head
                title={
                    isEditing
                        ? t('Edit: :title', { title: article.title })
                        : t('New article')
                }
            />

            <form onSubmit={submit} className="space-y-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title={isEditing ? t('Edit article') : t('New article')}
                        description={
                            article?.updated_at
                                ? t('Last saved :date', {
                                      date: formatDateTime(article.updated_at),
                                  })
                                : t('Write a help article for your customers.')
                        }
                    />
                    <div className="flex items-center gap-2">
                        {article?.is_published && (
                            <Button asChild variant="outline" size="sm">
                                <a
                                    href={publicArticle.url(article.slug)}
                                    target="_blank"
                                    rel="noreferrer"
                                >
                                    <ExternalLink /> {t('View live')}
                                </a>
                            </Button>
                        )}
                        {article && (
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                className="text-destructive hover:text-destructive"
                                onClick={() => {
                                    if (
                                        window.confirm(
                                            t(
                                                'Delete “:title”? This cannot be undone.',
                                                { title: article.title },
                                            ),
                                        )
                                    ) {
                                        router.delete(destroy.url(article.id));
                                    }
                                }}
                            >
                                <Trash2 /> {t('Delete')}
                            </Button>
                        )}
                    </div>
                </div>

                {sections.length === 0 ? (
                    <p className="rounded-lg border border-dashed p-6 text-center text-sm text-muted-foreground">
                        {t(
                            'Create a category and a section first, then come back to write articles.',
                        )}{' '}
                        <Link href={index()} className="text-primary underline">
                            {t('Go to the help center')}
                        </Link>
                    </p>
                ) : (
                    <div className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_16rem]">
                        <div className="space-y-5">
                            <div className="grid gap-2">
                                <Label htmlFor="title">{t('Title')}</Label>
                                <Input
                                    id="title"
                                    value={form.data.title}
                                    onChange={(event) => {
                                        form.setData((data) => ({
                                            ...data,
                                            title: event.target.value,
                                            slug: slugTouched
                                                ? data.slug
                                                : slugify(event.target.value),
                                        }));
                                    }}
                                    placeholder={t(
                                        'e.g. Resetting your password',
                                    )}
                                    autoFocus={!isEditing}
                                />
                                <InputError message={form.errors.title} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="slug">{t('URL slug')}</Label>
                                <div className="flex items-center rounded-md border shadow-xs focus-within:ring-[3px] focus-within:ring-ring/50 dark:bg-input/30">
                                    <span className="pl-3 text-sm whitespace-nowrap text-muted-foreground">
                                        /help/articles/
                                    </span>
                                    <input
                                        id="slug"
                                        value={form.data.slug}
                                        onChange={(event) => {
                                            setSlugTouched(true);
                                            form.setData(
                                                'slug',
                                                event.target.value,
                                            );
                                        }}
                                        className="h-9 min-w-0 flex-1 bg-transparent pr-3 text-sm outline-none"
                                    />
                                </div>
                                <InputError message={form.errors.slug} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="excerpt">
                                    {t('Summary')}{' '}
                                    <span className="font-normal text-muted-foreground">
                                        {t('(shown in search results)')}
                                    </span>
                                </Label>
                                <textarea
                                    id="excerpt"
                                    rows={2}
                                    value={form.data.excerpt}
                                    onChange={(event) =>
                                        form.setData(
                                            'excerpt',
                                            event.target.value,
                                        )
                                    }
                                    className="rounded-md border bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30"
                                />
                                <InputError message={form.errors.excerpt} />
                            </div>

                            <div className="grid gap-2">
                                <Label>{t('Body')}</Label>
                                <RichTextEditor
                                    value={form.data.body}
                                    onChange={(html) =>
                                        form.setData('body', html)
                                    }
                                    placeholder={t('Write the article…')}
                                    minHeight="22rem"
                                />
                                <InputError message={form.errors.body} />
                            </div>
                        </div>

                        <aside className="space-y-5">
                            <div className="grid gap-2">
                                <Label>{t('Status')}</Label>
                                <div className="grid grid-cols-2 gap-1 rounded-lg border p-1">
                                    {statuses.map((status) => (
                                        <button
                                            key={status.value}
                                            type="button"
                                            onClick={() =>
                                                form.setData(
                                                    'status',
                                                    status.value,
                                                )
                                            }
                                            className={cn(
                                                'rounded-md px-2 py-1.5 text-sm transition-colors',
                                                form.data.status ===
                                                    status.value
                                                    ? status.value ===
                                                      'published'
                                                        ? 'bg-emerald-500/15 font-medium text-emerald-700 dark:text-emerald-300'
                                                        : 'bg-muted font-medium'
                                                    : 'text-muted-foreground hover:bg-accent',
                                            )}
                                        >
                                            {status.label}
                                        </button>
                                    ))}
                                </div>
                                {article?.published_at && (
                                    <p className="text-xs text-muted-foreground">
                                        {t('First published :date', {
                                            date: formatDateTime(
                                                article.published_at,
                                            ),
                                        })}
                                    </p>
                                )}
                                <InputError message={form.errors.status} />
                            </div>

                            <div className="grid gap-2">
                                <Label>{t('Section')}</Label>
                                <Select
                                    value={
                                        form.data.section_id
                                            ? String(form.data.section_id)
                                            : undefined
                                    }
                                    onValueChange={(value) =>
                                        form.setData(
                                            'section_id',
                                            Number(value),
                                        )
                                    }
                                >
                                    <SelectTrigger className="w-full">
                                        <SelectValue
                                            placeholder={t('Choose a section')}
                                        />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {Object.entries(groupedSections).map(
                                            ([category, options]) => (
                                                <SelectGroup key={category}>
                                                    <SelectLabel>
                                                        {category}
                                                    </SelectLabel>
                                                    {options.map((section) => (
                                                        <SelectItem
                                                            key={section.id}
                                                            value={String(
                                                                section.id,
                                                            )}
                                                        >
                                                            {section.name}
                                                        </SelectItem>
                                                    ))}
                                                </SelectGroup>
                                            ),
                                        )}
                                    </SelectContent>
                                </Select>
                                <InputError message={form.errors.section_id} />
                            </div>

                            <Button
                                type="submit"
                                className="w-full"
                                disabled={form.processing}
                            >
                                {isEditing
                                    ? t('Save changes')
                                    : t('Create article')}
                            </Button>
                        </aside>
                    </div>
                )}
            </form>
        </>
    );
}
