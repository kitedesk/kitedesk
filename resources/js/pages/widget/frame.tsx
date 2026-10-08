import { Head } from '@inertiajs/react';
import { CircleCheck, Paperclip, X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { Captcha } from '@/components/captcha';
import InputError from '@/components/input-error';
import { CategorySelect } from '@/components/tickets/category-select';
import {
    fieldsForCategory,
    TicketFormFields,
} from '@/components/tickets/form-fields';
import type { CustomFieldValue } from '@/components/tickets/form-fields';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTranslation } from '@/hooks/use-translation';
import { formatFileSize } from '@/lib/tickets';
import { store } from '@/routes/widget/tickets';
import type { CategoryNode, FormFields } from '@/types';

type Errors = Record<string, string | undefined>;

const emptyRequest = {
    name: '',
    email: '',
    subject: '',
    body: '',
    category_id: null as number | null,
    custom_fields: {} as Record<string, CustomFieldValue>,
    attachments: [] as File[],
    captcha: '',
};

/**
 * Tell the embed script on the host page (resources/js/widget/loader.ts). Its origin isn't
 * known here, and the messages carry nothing private.
 */
function notifyHost(type: 'ready' | 'close') {
    window.parent.postMessage({ type: `kitedesk:${type}` }, '*');
}

/**
 * The support widget's panel, shown in an iframe on other websites. It posts with `fetch`
 * because browsers don't send our cookies to third-party frames, so Inertia forms (which
 * need the session's CSRF token) can't be used.
 */
export default function WidgetFrame({
    greeting,
    brandName,
    categories,
    forms,
    defaultFormId,
}: {
    greeting: string | null;
    brandName: string;
    categories: CategoryNode[];
    forms: FormFields;
    defaultFormId: number | null;
}) {
    const { t } = useTranslation();
    const fileInput = useRef<HTMLInputElement>(null);
    const [data, setData] = useState(emptyRequest);
    const [errors, setErrors] = useState<Errors>({});
    const [processing, setProcessing] = useState(false);
    const [sent, setSent] = useState<{ reference: string | null } | null>(null);
    const fields = fieldsForCategory(
        categories,
        forms,
        data.category_id,
        defaultFormId,
    );

    const set = <K extends keyof typeof emptyRequest>(
        key: K,
        value: (typeof emptyRequest)[K],
    ) => setData((current) => ({ ...current, [key]: value }));

    useEffect(() => {
        // `KiteDesk.identify()` on the host page fills in who the visitor is.
        const receive = (event: MessageEvent) => {
            const message = event.data as {
                type?: string;
                name?: unknown;
                email?: unknown;
            } | null;

            if (
                event.source !== window.parent ||
                message?.type !== 'kitedesk:identify'
            ) {
                return;
            }

            setData((current) => ({
                ...current,
                name:
                    current.name ||
                    (typeof message.name === 'string' ? message.name : ''),
                email:
                    current.email ||
                    (typeof message.email === 'string' ? message.email : ''),
            }));
        };

        window.addEventListener('message', receive);
        notifyHost('ready');

        return () => window.removeEventListener('message', receive);
    }, []);

    const submit = async (event: React.FormEvent) => {
        event.preventDefault();
        setProcessing(true);

        const body = new FormData();
        body.append('name', data.name);
        body.append('email', data.email);
        body.append('subject', data.subject);
        body.append('body', data.body);
        body.append('cf-turnstile-response', data.captcha);

        if (data.category_id !== null) {
            body.append('category_id', String(data.category_id));
        }

        // Only send the fields of the form that applies to the chosen category.
        for (const field of fields) {
            const value = data.custom_fields[field.key];

            if (value !== undefined) {
                body.append(
                    `custom_fields[${field.key}]`,
                    typeof value === 'boolean' ? (value ? '1' : '0') : value,
                );
            }
        }

        data.attachments.forEach((file) => body.append('attachments[]', file));

        try {
            const response = await fetch(store.url(), {
                method: 'POST',
                headers: { Accept: 'application/json' },
                body,
            });

            if (response.status === 201) {
                const result = (await response.json()) as {
                    reference: string | null;
                };
                setSent(result);
                setErrors({});

                return;
            }

            if (response.status === 422) {
                const result = (await response.json()) as {
                    errors?: Record<string, string[]>;
                };
                setErrors(
                    Object.fromEntries(
                        Object.entries(result.errors ?? {}).map(
                            ([key, messages]) => [key, messages[0]],
                        ),
                    ),
                );

                return;
            }

            setErrors({
                form:
                    response.status === 429
                        ? t('Too many requests. Wait a minute and try again.')
                        : t("We couldn't send your request. Please try again."),
            });
        } catch {
            setErrors({
                form: t("We couldn't send your request. Please try again."),
            });
        } finally {
            setProcessing(false);
        }
    };

    return (
        <>
            <Head title={brandName} />
            <div className="flex h-dvh flex-col bg-background text-foreground">
                <header className="flex items-start justify-between gap-3 bg-primary px-5 py-4 text-primary-foreground">
                    <div className="min-w-0">
                        <h1 className="truncate text-base font-semibold">
                            {brandName}
                        </h1>
                        <p className="text-sm text-pretty opacity-90">
                            {greeting ??
                                t(
                                    "Tell us what's going on and we'll get back to you by email.",
                                )}
                        </p>
                    </div>
                    <button
                        type="button"
                        onClick={() => notifyHost('close')}
                        aria-label={t('Close')}
                        className="-mr-1 rounded-md p-1 opacity-90 hover:bg-white/15 hover:opacity-100 focus-visible:ring-2 focus-visible:ring-white/70 focus-visible:outline-none"
                    >
                        <X className="size-5" />
                    </button>
                </header>

                {sent ? (
                    <div className="flex flex-1 flex-col items-center justify-center gap-3 p-8 text-center">
                        <CircleCheck className="size-10 text-primary" />
                        <h2 className="text-lg font-semibold">
                            {t('Request sent')}
                        </h2>
                        <p className="text-sm text-pretty text-muted-foreground">
                            {sent.reference
                                ? t(
                                      'We received request :number and emailed you a link to follow it. Replies will arrive by email too.',
                                      { number: sent.reference },
                                  )
                                : t(
                                      'Thanks! We emailed you a link to follow your request.',
                                  )}
                        </p>
                        <Button
                            variant="outline"
                            onClick={() => {
                                setData((current) => ({
                                    ...emptyRequest,
                                    name: current.name,
                                    email: current.email,
                                }));
                                setSent(null);
                            }}
                        >
                            {t('Send another request')}
                        </Button>
                    </div>
                ) : (
                    <form
                        onSubmit={submit}
                        className="flex min-h-0 flex-1 flex-col"
                    >
                        <div className="flex-1 space-y-4 overflow-y-auto p-5">
                            <div className="grid gap-2">
                                <Label htmlFor="name">{t('Your name')}</Label>
                                <Input
                                    id="name"
                                    value={data.name}
                                    onChange={(event) =>
                                        set('name', event.target.value)
                                    }
                                    autoComplete="name"
                                    required
                                />
                                <InputError message={errors.name} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="email">
                                    {t('Email address')}
                                </Label>
                                <Input
                                    id="email"
                                    type="email"
                                    value={data.email}
                                    onChange={(event) =>
                                        set('email', event.target.value)
                                    }
                                    autoComplete="email"
                                    required
                                />
                                <InputError message={errors.email} />
                            </div>

                            {categories.length > 0 && (
                                <CategorySelect
                                    categories={categories}
                                    value={data.category_id}
                                    onChange={(id) => set('category_id', id)}
                                    errors={errors.category_id}
                                />
                            )}

                            <div className="grid gap-2">
                                <Label htmlFor="subject">{t('Subject')}</Label>
                                <Input
                                    id="subject"
                                    value={data.subject}
                                    onChange={(event) =>
                                        set('subject', event.target.value)
                                    }
                                    required
                                />
                                <InputError message={errors.subject} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="body">{t('Description')}</Label>
                                <textarea
                                    id="body"
                                    rows={5}
                                    value={data.body}
                                    onChange={(event) =>
                                        set('body', event.target.value)
                                    }
                                    required
                                    className="rounded-md border bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30"
                                />
                                <InputError message={errors.body} />
                            </div>

                            <TicketFormFields
                                fields={fields}
                                values={data.custom_fields}
                                errors={errors}
                                onChange={(key, value) =>
                                    set('custom_fields', {
                                        ...data.custom_fields,
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
                                        set('attachments', [
                                            ...data.attachments,
                                            ...Array.from(
                                                event.target.files ?? [],
                                            ),
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
                                {data.attachments.length > 0 && (
                                    <ul className="flex flex-wrap gap-2">
                                        {data.attachments.map((file, i) => (
                                            <li
                                                key={`${file.name}-${i}`}
                                                className="inline-flex items-center gap-1.5 rounded-md border bg-muted/50 py-1 pr-1 pl-2 text-xs"
                                            >
                                                <span className="max-w-32 truncate">
                                                    {file.name}
                                                </span>
                                                <span className="text-muted-foreground">
                                                    {formatFileSize(file.size)}
                                                </span>
                                                <button
                                                    type="button"
                                                    aria-label={t(
                                                        'Remove :name',
                                                        { name: file.name },
                                                    )}
                                                    onClick={() =>
                                                        set(
                                                            'attachments',
                                                            data.attachments.filter(
                                                                (_, j) =>
                                                                    j !== i,
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
                                <InputError
                                    message={
                                        errors.attachments ??
                                        Object.entries(errors).find(([key]) =>
                                            key.startsWith('attachments.'),
                                        )?.[1]
                                    }
                                />
                            </div>

                            <Captcha
                                onToken={(token) => set('captcha', token)}
                                resetKey={errors}
                                error={errors['cf-turnstile-response']}
                            />
                        </div>

                        <div className="space-y-2 border-t p-4">
                            <InputError message={errors.form} />
                            <Button
                                type="submit"
                                className="w-full"
                                disabled={processing}
                            >
                                {processing
                                    ? t('Sending…')
                                    : t('Submit request')}
                            </Button>
                        </div>
                    </form>
                )}
            </div>
        </>
    );
}
