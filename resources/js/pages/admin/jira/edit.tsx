import { Head, useForm, useHttp } from '@inertiajs/react';
import { Copy, PlugZap, RefreshCw, TriangleAlert, X } from 'lucide-react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import { ConfirmAction } from '@/components/admin/confirm-action';
import { AdminPageHeader } from '@/components/admin/page-header';
import { TextField } from '@/components/admin/text-field';
import InputError from '@/components/input-error';
import { CustomStatusSelect } from '@/components/tickets/custom-status-select';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Skeleton } from '@/components/ui/skeleton';
import { Spinner } from '@/components/ui/spinner';
import { useClipboard } from '@/hooks/use-clipboard';
import { useTranslation } from '@/hooks/use-translation';
import { relativeTime } from '@/lib/tickets';
import {
    edit,
    issueTypes as issueTypesRoute,
    secret,
    test,
    update,
} from '@/routes/admin/jira';
import type { CustomStatusOption, Option } from '@/types';

type Settings = {
    site_url: string;
    email: string;
    api_token: string;
    expires_on: string;
    default_project: string;
    default_issue_type: string;
    status_mapping: Record<string, string>;
    /** Rules for exact Jira statuses; an empty ticket status means "don't change". */
    status_rules: {
        status_id: string;
        status_name: string;
        ticket_status_id: string;
    }[];
};

const NONE = '__none';

export default function JiraSettingsPage({
    settings,
    hasToken,
    connected,
    error,
    categories,
    statuses,
    projects,
    jiraStatuses,
    webhook,
}: {
    settings: Settings;
    hasToken: boolean;
    connected: boolean;
    error: { message: string; at: string } | null;
    categories: Option[];
    statuses: CustomStatusOption[];
    /** Loaded after the page, since it asks Jira. */
    projects?: { key: string; name: string }[];
    /** Jira's workflow statuses; loaded after the page, since it asks Jira. */
    jiraStatuses?: { id: string; name: string; category: string | null }[];
    webhook: { url: string; secret: string | null };
}) {
    const { t } = useTranslation();
    const form = useForm(settings);
    const testing = useForm({ site_url: '', email: '', api_token: '' });
    const http = useHttp<
        Record<string, never>,
        { issue_types: { id: string; name: string }[] }
    >({});
    const [types, setTypes] = useState<{ id: string; name: string }[]>([]);
    const [, copy] = useClipboard();
    const errors = form.errors as Record<string, string | undefined>;

    // Issue types of the default project, for the default issue type.
    useEffect(() => {
        if (!connected || form.data.default_project === '') {
            setTypes([]);

            return;
        }

        http.get(
            issueTypesRoute.url({
                query: { project: form.data.default_project },
            }),
        )
            .then((answer) => setTypes(answer.issue_types))
            .catch(() => setTypes([]));
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [connected, form.data.default_project]);

    const copyText = (text: string) => {
        void copy(text);
        toast.success(t('Copied.'));
    };

    const testConnection = () => {
        testing.transform(() => ({
            site_url: form.data.site_url,
            email: form.data.email,
            api_token: form.data.api_token,
        }));
        testing.post(test.url(), { preserveScroll: true, preserveState: true });
    };

    return (
        <>
            <Head title="Jira" />
            <AdminPageHeader
                title="Jira"
                description={t(
                    'Agents create and link Jira Cloud issues from tickets. Status changes and comments in Jira come back to the ticket as internal notes, and internal notes go to Jira as comments.',
                )}
            />

            {error && (
                <div className="mb-6 flex max-w-3xl items-start gap-2 rounded-lg border border-destructive/40 bg-destructive/5 p-3 text-sm">
                    <TriangleAlert className="mt-0.5 size-4 shrink-0 text-destructive" />
                    <div>
                        <p>{error.message}</p>
                        <p className="text-xs text-muted-foreground">
                            {relativeTime(error.at)}
                        </p>
                    </div>
                </div>
            )}

            <form
                onSubmit={(event) => {
                    event.preventDefault();
                    form.put(update.url(), {
                        preserveScroll: true,
                        onSuccess: () => form.setData('api_token', ''),
                    });
                }}
                className="max-w-3xl space-y-10"
            >
                <section className="space-y-4">
                    <h2 className="font-medium">{t('Connection')}</h2>
                    <TextField
                        id="jira-site"
                        label={t('Jira site')}
                        value={form.data.site_url}
                        onChange={(value) => form.setData('site_url', value)}
                        placeholder="https://your-company.atlassian.net"
                        error={errors.site_url}
                    />
                    <div className="grid gap-4 sm:grid-cols-2">
                        <TextField
                            id="jira-email"
                            label={t('Jira account email')}
                            type="email"
                            value={form.data.email}
                            onChange={(value) => form.setData('email', value)}
                            error={errors.email}
                        />
                        <TextField
                            id="jira-token"
                            label={t('API token')}
                            type="password"
                            value={form.data.api_token}
                            onChange={(value) =>
                                form.setData('api_token', value)
                            }
                            placeholder={
                                hasToken ? t('Saved — leave blank to keep') : ''
                            }
                            error={errors.api_token}
                        />
                    </div>
                    <p className="text-sm text-muted-foreground">
                        {t(
                            'Create the token at id.atlassian.com → Security → API tokens, signed in as the account KiteDesk should act as. Issues and comments KiteDesk creates show that account as their author.',
                        )}
                    </p>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <TextField
                            id="jira-expires"
                            label={t('Token expires on')}
                            type="date"
                            value={form.data.expires_on}
                            onChange={(value) =>
                                form.setData('expires_on', value)
                            }
                            error={errors.expires_on}
                        />
                    </div>
                    <p className="text-sm text-muted-foreground">
                        {t(
                            'Optional. Admins are reminded two weeks before, and on the day.',
                        )}
                    </p>
                    <Button
                        type="button"
                        variant="outline"
                        onClick={testConnection}
                        disabled={testing.processing}
                    >
                        {testing.processing ? <Spinner /> : <PlugZap />}
                        {t('Test connection')}
                    </Button>
                </section>

                {connected && (
                    <section className="space-y-4">
                        <h2 className="font-medium">{t('New issues')}</h2>
                        <p className="text-sm text-muted-foreground">
                            {t(
                                'What the "Create issue" form on a ticket starts with. Agents can pick something else.',
                            )}
                        </p>
                        {projects === undefined ? (
                            <Skeleton className="h-9 w-full" />
                        ) : (
                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label>{t('Project')}</Label>
                                    <Select
                                        value={
                                            form.data.default_project || NONE
                                        }
                                        onValueChange={(value) => {
                                            form.setData((data) => ({
                                                ...data,
                                                default_project:
                                                    value === NONE ? '' : value,
                                                default_issue_type: '',
                                            }));
                                        }}
                                    >
                                        <SelectTrigger className="w-full">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value={NONE}>
                                                —
                                            </SelectItem>
                                            {projects.map((project) => (
                                                <SelectItem
                                                    key={project.key}
                                                    value={project.key}
                                                >
                                                    {project.name} (
                                                    {project.key})
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div className="grid gap-2">
                                    <Label>{t('Issue type')}</Label>
                                    <Select
                                        value={
                                            form.data.default_issue_type || NONE
                                        }
                                        onValueChange={(value) =>
                                            form.setData(
                                                'default_issue_type',
                                                value === NONE ? '' : value,
                                            )
                                        }
                                        disabled={
                                            form.data.default_project === ''
                                        }
                                    >
                                        <SelectTrigger className="w-full">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value={NONE}>
                                                —
                                            </SelectItem>
                                            {types.map((type) => (
                                                <SelectItem
                                                    key={type.id}
                                                    value={type.id}
                                                >
                                                    {type.name}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>
                            </div>
                        )}
                    </section>
                )}

                <section className="space-y-4">
                    <h2 className="font-medium">{t('Status changes')}</h2>
                    <p className="text-sm text-muted-foreground">
                        {t(
                            'When a linked issue moves into one of Jira\'s status groups, move the ticket to this status. "Done" only applies once every issue linked to the ticket is done. Closed tickets never change.',
                        )}
                    </p>
                    <div className="divide-y rounded-lg border">
                        {categories.map((category) => {
                            const value =
                                form.data.status_mapping[category.value] ?? '';

                            return (
                                <div
                                    key={category.value}
                                    className="grid items-center gap-2 p-3 sm:grid-cols-[10rem_1fr]"
                                >
                                    <Label>{category.label}</Label>
                                    <div className="flex items-center gap-2">
                                        <CustomStatusSelect
                                            value={
                                                value === '' ? undefined : value
                                            }
                                            statuses={statuses}
                                            placeholder={t("Don't change")}
                                            onChange={(id) =>
                                                form.setData('status_mapping', {
                                                    ...form.data.status_mapping,
                                                    [category.value]: id,
                                                })
                                            }
                                        />
                                        {value !== '' && (
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="sm"
                                                onClick={() =>
                                                    form.setData(
                                                        'status_mapping',
                                                        {
                                                            ...form.data
                                                                .status_mapping,
                                                            [category.value]:
                                                                '',
                                                        },
                                                    )
                                                }
                                            >
                                                {t("Don't change")}
                                            </Button>
                                        )}
                                    </div>
                                    <InputError
                                        className="sm:col-start-2"
                                        message={
                                            errors[
                                                `status_mapping.${category.value}`
                                            ]
                                        }
                                    />
                                </div>
                            );
                        })}
                    </div>
                </section>

                {connected && (
                    <section className="space-y-4">
                        <h2 className="font-medium">
                            {t('Rules for specific Jira statuses')}
                        </h2>
                        <p className="text-sm text-muted-foreground">
                            {t(
                                'For statuses that need their own handling, such as "Waiting for customer" or "Won\'t fix". A rule here wins over its group above.',
                            )}
                        </p>
                        {form.data.status_rules.length > 0 && (
                            <div className="divide-y rounded-lg border">
                                {form.data.status_rules.map((rule, index) => (
                                    <div
                                        key={rule.status_id}
                                        className="grid items-center gap-2 p-3 sm:grid-cols-[12rem_1fr_auto]"
                                    >
                                        <Label>{rule.status_name}</Label>
                                        <CustomStatusSelect
                                            value={
                                                rule.ticket_status_id === ''
                                                    ? undefined
                                                    : rule.ticket_status_id
                                            }
                                            statuses={statuses}
                                            placeholder={t("Don't change")}
                                            onChange={(id) =>
                                                form.setData(
                                                    'status_rules',
                                                    form.data.status_rules.map(
                                                        (current, i) =>
                                                            i === index
                                                                ? {
                                                                      ...current,
                                                                      ticket_status_id:
                                                                          id,
                                                                  }
                                                                : current,
                                                    ),
                                                )
                                            }
                                        />
                                        <div className="flex gap-1">
                                            {rule.ticket_status_id !== '' && (
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="sm"
                                                    onClick={() =>
                                                        form.setData(
                                                            'status_rules',
                                                            form.data.status_rules.map(
                                                                (current, i) =>
                                                                    i === index
                                                                        ? {
                                                                              ...current,
                                                                              ticket_status_id:
                                                                                  '',
                                                                          }
                                                                        : current,
                                                            ),
                                                        )
                                                    }
                                                >
                                                    {t("Don't change")}
                                                </Button>
                                            )}
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="icon"
                                                title={t('Remove rule')}
                                                onClick={() =>
                                                    form.setData(
                                                        'status_rules',
                                                        form.data.status_rules.filter(
                                                            (_, i) =>
                                                                i !== index,
                                                        ),
                                                    )
                                                }
                                            >
                                                <X />
                                            </Button>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}
                        {jiraStatuses === undefined ? (
                            <Skeleton className="h-9 w-64" />
                        ) : jiraStatuses.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                {t('Could not load the statuses from Jira.')}
                            </p>
                        ) : (
                            <Select
                                value=""
                                onValueChange={(id) => {
                                    const status = jiraStatuses.find(
                                        (item) => item.id === id,
                                    );

                                    if (status) {
                                        form.setData('status_rules', [
                                            ...form.data.status_rules,
                                            {
                                                status_id: status.id,
                                                status_name: status.name,
                                                ticket_status_id: '',
                                            },
                                        ]);
                                    }
                                }}
                            >
                                <SelectTrigger className="w-64">
                                    <SelectValue
                                        placeholder={t('Add a rule for…')}
                                    />
                                </SelectTrigger>
                                <SelectContent>
                                    {jiraStatuses
                                        .filter(
                                            (status) =>
                                                !form.data.status_rules.some(
                                                    (rule) =>
                                                        rule.status_id ===
                                                        status.id,
                                                ),
                                        )
                                        .map((status) => (
                                            <SelectItem
                                                key={status.id}
                                                value={status.id}
                                            >
                                                {status.name}
                                                {status.category && (
                                                    <span className="text-xs text-muted-foreground">
                                                        {
                                                            categories.find(
                                                                (category) =>
                                                                    category.value ===
                                                                    status.category,
                                                            )?.label
                                                        }
                                                    </span>
                                                )}
                                            </SelectItem>
                                        ))}
                                </SelectContent>
                            </Select>
                        )}
                    </section>
                )}

                <div className="flex justify-end">
                    <Button type="submit" disabled={form.processing}>
                        {t('Save')}
                    </Button>
                </div>
            </form>

            {webhook.secret && (
                <section className="mt-12 max-w-3xl space-y-4 border-t pt-8">
                    <h2 className="font-medium">{t('Webhook in Jira')}</h2>
                    <p className="text-sm text-muted-foreground">
                        {t(
                            'So tickets hear about changes, a Jira admin creates one webhook under Jira settings → System → WebHooks, with:',
                        )}
                    </p>
                    <dl className="space-y-3 text-sm">
                        {[
                            { label: t('URL'), value: webhook.url },
                            { label: t('Secret'), value: webhook.secret },
                        ].map((row) => (
                            <div key={row.label} className="grid gap-1">
                                <dt className="font-medium">{row.label}</dt>
                                <dd className="flex items-center gap-2">
                                    <code className="min-w-0 flex-1 truncate rounded bg-muted px-2 py-1.5">
                                        {row.value}
                                    </code>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        title={t('Copy')}
                                        onClick={() => copyText(row.value)}
                                    >
                                        <Copy />
                                    </Button>
                                </dd>
                            </div>
                        ))}
                        <div className="grid gap-1">
                            <dt className="font-medium">{t('Events')}</dt>
                            <dd className="text-muted-foreground">
                                {t(
                                    'Issue: updated, deleted. Comment: created.',
                                )}
                            </dd>
                        </div>
                        <div className="grid gap-1">
                            <dt className="font-medium">{t('JQL filter')}</dt>
                            <dd className="text-muted-foreground">
                                {t(
                                    'Optional, e.g. project in (ENG, OPS), to limit it to the projects you escalate to.',
                                )}
                            </dd>
                        </div>
                    </dl>
                    <ConfirmAction
                        trigger={
                            <Button type="button" variant="outline">
                                <RefreshCw /> {t('New secret')}
                            </Button>
                        }
                        title={t('Create a new webhook secret?')}
                        description={t(
                            "Jira's deliveries are refused until the webhook in Jira has the new secret.",
                        )}
                        href={secret.url()}
                        method="post"
                    />
                </section>
            )}
        </>
    );
}

JiraSettingsPage.layout = {
    breadcrumbs: [
        { title: 'Admin center', href: '/admin' },
        { title: 'Jira', href: edit() },
    ],
};
