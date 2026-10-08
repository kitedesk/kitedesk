import { Link, useForm } from '@inertiajs/react';
import { BookOpen, Paperclip, X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { Captcha } from '@/components/captcha';
import InputError from '@/components/input-error';
import { CategorySelect } from '@/components/tickets/category-select';
import {
    fieldsForCategory,
    TicketFormFields,
} from '@/components/tickets/form-fields';
import type { CustomFieldValue } from '@/components/tickets/form-fields';
import { RichTextEditor } from '@/components/tickets/rich-text-editor';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTranslation } from '@/hooks/use-translation';
import { formatFileSize } from '@/lib/tickets';
import { search } from '@/routes/help';
import { show as showArticle } from '@/routes/help/articles';
import type { CategoryNode, FormFields } from '@/types';

type Suggestion = {
    id: number;
    title: string;
    slug: string;
    excerpt: string | null;
};

/**
 * "Submit a request" form shared by the portal (signed in) and the guest form, which also
 * asks for a name, an email address and (when configured) a CAPTCHA.
 */
export function RequestForm({
    categories,
    forms,
    defaultFormId,
    action,
    guest = false,
}: {
    categories: CategoryNode[];
    forms: FormFields;
    defaultFormId: number | null;
    action: string;
    guest?: boolean;
}) {
    const { t } = useTranslation();
    const fileInput = useRef<HTMLInputElement>(null);
    const [suggestions, setSuggestions] = useState<Suggestion[]>([]);
    const form = useForm({
        name: '',
        email: '',
        'cf-turnstile-response': '',
        category_id: null as number | null,
        subject: '',
        body: '',
        custom_fields: {} as Record<string, CustomFieldValue>,
        attachments: [] as File[],
    });
    const fields = fieldsForCategory(
        categories,
        forms,
        form.data.category_id,
        defaultFormId,
    );

    // Ticket deflection: suggest help articles while the customer describes the problem.
    useEffect(() => {
        const subject = form.data.subject.trim();

        if (subject.length < 4) {
            setSuggestions([]);

            return;
        }

        const timeout = window.setTimeout(async () => {
            const response = await fetch(
                search.url({ query: { q: subject } }),
                {
                    headers: { Accept: 'application/json' },
                },
            );

            if (response.ok) {
                setSuggestions(
                    (
                        (await response.json()) as { articles: Suggestion[] }
                    ).articles.slice(0, 4),
                );
            }
        }, 350);

        return () => window.clearTimeout(timeout);
    }, [form.data.subject]);

    const submit = (event: React.FormEvent) => {
        event.preventDefault();

        // Only send the fields of the form that applies to the chosen category.
        const keys = fields.map((field) => field.key);
        form.transform(({ name, email, ...data }) => ({
            ...data,
            ...(guest
                ? { name, email }
                : { 'cf-turnstile-response': undefined }),
            custom_fields: Object.fromEntries(
                Object.entries(data.custom_fields).filter(([key]) =>
                    keys.includes(key),
                ),
            ),
        }));

        form.post(action, {
            forceFormData: form.data.attachments.length > 0,
        });
    };

    return (
        <form
            onSubmit={submit}
            className="mx-auto w-full max-w-2xl space-y-6 px-4 py-10"
        >
            <div>
                <h1 className="text-2xl font-semibold tracking-tight">
                    {t('Submit a request')}
                </h1>
                <p className="text-sm text-muted-foreground">
                    {t(
                        "Tell us what's going on and we'll get back to you by email.",
                    )}
                </p>
            </div>

            {guest && (
                <div className="grid gap-4 sm:grid-cols-2">
                    <div className="grid gap-2">
                        <Label htmlFor="name">{t('Your name')}</Label>
                        <Input
                            id="name"
                            value={form.data.name}
                            onChange={(event) =>
                                form.setData('name', event.target.value)
                            }
                            autoComplete="name"
                            required
                        />
                        <InputError message={form.errors.name} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="email">{t('Email address')}</Label>
                        <Input
                            id="email"
                            type="email"
                            value={form.data.email}
                            onChange={(event) =>
                                form.setData('email', event.target.value)
                            }
                            autoComplete="email"
                            required
                        />
                        <InputError message={form.errors.email} />
                        <p className="text-xs text-muted-foreground">
                            {t(
                                "We'll send replies and a link to follow your request here.",
                            )}
                        </p>
                    </div>
                </div>
            )}

            {categories.length > 0 && (
                <CategorySelect
                    categories={categories}
                    value={form.data.category_id}
                    onChange={(id) => form.setData('category_id', id)}
                    errors={form.errors.category_id}
                />
            )}

            <div className="grid gap-2">
                <Label htmlFor="subject">{t('Subject')}</Label>
                <Input
                    id="subject"
                    value={form.data.subject}
                    onChange={(event) =>
                        form.setData('subject', event.target.value)
                    }
                    placeholder={t("e.g. I can't reset my password")}
                    autoFocus={!guest}
                />
                <InputError message={form.errors.subject} />
            </div>

            {suggestions.length > 0 && (
                <div className="rounded-xl border border-primary/20 bg-primary/5 p-4">
                    <p className="mb-2 flex items-center gap-1.5 text-sm font-medium">
                        <BookOpen className="size-4 text-primary" />{' '}
                        {t('These articles might help')}
                    </p>
                    <ul className="space-y-1.5">
                        {suggestions.map((article) => (
                            <li key={article.id}>
                                <Link
                                    href={showArticle(article.slug)}
                                    className="text-sm text-primary hover:underline"
                                >
                                    {article.title}
                                </Link>
                                {article.excerpt && (
                                    <p className="line-clamp-1 text-xs text-muted-foreground">
                                        {article.excerpt}
                                    </p>
                                )}
                            </li>
                        ))}
                    </ul>
                </div>
            )}

            <div className="grid gap-2">
                <Label>{t('Description')}</Label>
                <RichTextEditor
                    value={form.data.body}
                    onChange={(html) => form.setData('body', html)}
                    placeholder={t(
                        'Please share as much detail as you can — steps, error messages, screenshots…',
                    )}
                    minHeight="12rem"
                />
                <InputError message={form.errors.body} />
            </div>

            <TicketFormFields
                fields={fields}
                values={form.data.custom_fields}
                errors={form.errors as Record<string, string | undefined>}
                onChange={(key, value) =>
                    form.setData('custom_fields', {
                        ...form.data.custom_fields,
                        [key]: value,
                    })
                }
            />

            <div className="space-y-2">
                <input
                    ref={fileInput}
                    type="file"
                    multiple
                    className="hidden"
                    onChange={(event) => {
                        form.setData('attachments', [
                            ...form.data.attachments,
                            ...Array.from(event.target.files ?? []),
                        ]);
                        event.target.value = '';
                    }}
                />
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    onClick={() => fileInput.current?.click()}
                >
                    <Paperclip /> {t('Attach files')}
                </Button>
                {form.data.attachments.length > 0 && (
                    <ul className="flex flex-wrap gap-2">
                        {form.data.attachments.map((file, i) => (
                            <li
                                key={`${file.name}-${i}`}
                                className="inline-flex items-center gap-1.5 rounded-md border bg-muted/50 py-1 pr-1 pl-2 text-xs"
                            >
                                <span className="max-w-40 truncate">
                                    {file.name}
                                </span>
                                <span className="text-muted-foreground">
                                    {formatFileSize(file.size)}
                                </span>
                                <button
                                    type="button"
                                    aria-label={t('Remove :name', {
                                        name: file.name,
                                    })}
                                    onClick={() =>
                                        form.setData(
                                            'attachments',
                                            form.data.attachments.filter(
                                                (_, j) => j !== i,
                                            ),
                                        )
                                    }
                                    className="rounded p-0.5 hover:bg-background"
                                >
                                    <X className="size-3" />
                                </button>
                            </li>
                        ))}
                    </ul>
                )}
            </div>

            {guest && (
                <Captcha
                    onToken={(token) =>
                        form.setData('cf-turnstile-response', token)
                    }
                    resetKey={form.errors}
                    error={form.errors['cf-turnstile-response']}
                />
            )}

            <div className="flex justify-end">
                <Button type="submit" size="lg" disabled={form.processing}>
                    {t('Submit request')}
                </Button>
            </div>
        </form>
    );
}
