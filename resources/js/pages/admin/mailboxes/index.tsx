import { Head, router, useForm } from '@inertiajs/react';
import {
    AlertTriangle,
    Copy,
    Inbox,
    Pencil,
    PlugZap,
    Plus,
    Trash2,
} from 'lucide-react';
import { useState } from 'react';
import { ConfirmAction } from '@/components/admin/confirm-action';
import { AdminPageHeader } from '@/components/admin/page-header';
import { TextField } from '@/components/admin/text-field';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useClipboard } from '@/hooks/use-clipboard';
import { useTranslation } from '@/hooks/use-translation';
import { relativeTime } from '@/lib/tickets';
import { destroy, index, store, test, update } from '@/routes/admin/mailboxes';
import type { CategoryNode, NamedRecord, Option } from '@/types';

type Driver = 'imap' | 'postmark' | 'mailgun';

type Mailbox = {
    id: number;
    name: string;
    address: string;
    is_default: boolean;
    is_active: boolean;
    default_group_id: number | null;
    default_category_id: number | null;
    driver: Driver;
    imap_host: string | null;
    imap_port: number | null;
    imap_encryption: string | null;
    imap_username: string | null;
    has_imap_password: boolean;
    imap_folder: string;
    delete_after_import: boolean;
    inbound_secret: string | null;
    webhook_url: string | null;
    last_polled_at: string | null;
    last_error: string | null;
};

type Props = {
    mailboxes: Mailbox[];
    drivers: Option<Driver>[];
    groups: NamedRecord[];
    categories: CategoryNode[];
};

const NONE = '__none';

export default function MailboxesIndex({
    mailboxes,
    drivers,
    groups,
    categories,
}: Props) {
    const { t } = useTranslation();
    const [editing, setEditing] = useState<Mailbox | 'new' | null>(null);

    return (
        <>
            <Head title={t('Email')} />
            <AdminPageHeader
                title={t('Email')}
                description={t(
                    'Support addresses. Email sent to them becomes tickets, and replies to those tickets go out from the same address.',
                )}
                actions={
                    <Button onClick={() => setEditing('new')}>
                        <Plus /> {t('Add mailbox')}
                    </Button>
                }
            />

            {mailboxes.length === 0 ? (
                <div className="rounded-xl border border-dashed px-6 py-16 text-center text-sm text-muted-foreground">
                    <Inbox className="mx-auto mb-2 size-6" />
                    {t(
                        'No mailboxes yet. Add your support address to receive tickets by email.',
                    )}
                </div>
            ) : (
                <ul className="divide-y overflow-hidden rounded-xl border bg-card shadow-xs">
                    {mailboxes.map((mailbox) => (
                        <li key={mailbox.id} className="space-y-2 px-4 py-3">
                            <div className="flex items-center gap-3">
                                <div className="min-w-0 flex-1">
                                    <p className="flex flex-wrap items-center gap-2 font-medium">
                                        {mailbox.address}
                                        {mailbox.is_default && (
                                            <Badge variant="secondary">
                                                {t('Default')}
                                            </Badge>
                                        )}
                                        {!mailbox.is_active && (
                                            <Badge variant="outline">
                                                {t('Inactive')}
                                            </Badge>
                                        )}
                                    </p>
                                    <p className="truncate text-xs text-muted-foreground">
                                        {mailbox.name} ·{' '}
                                        {
                                            drivers.find(
                                                (driver) =>
                                                    driver.value ===
                                                    mailbox.driver,
                                            )?.label
                                        }
                                        {mailbox.driver === 'imap' &&
                                            ` · ${
                                                mailbox.last_polled_at
                                                    ? t('checked :time', {
                                                          time: relativeTime(
                                                              mailbox.last_polled_at,
                                                          ),
                                                      })
                                                    : t('not checked yet')
                                            }`}
                                    </p>
                                </div>
                                {mailbox.driver === 'imap' && (
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={() =>
                                            router.post(
                                                test.url(mailbox.id),
                                                {},
                                                { preserveScroll: true },
                                            )
                                        }
                                    >
                                        <PlugZap /> {t('Test connection')}
                                    </Button>
                                )}
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    title={t('Edit')}
                                    onClick={() => setEditing(mailbox)}
                                >
                                    <Pencil />
                                </Button>
                                <ConfirmAction
                                    trigger={
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            title={t('Delete')}
                                        >
                                            <Trash2 />
                                        </Button>
                                    }
                                    title={t('Remove :address?', {
                                        address: mailbox.address,
                                    })}
                                    description={t(
                                        'Email to this address will no longer create tickets. Existing tickets reply from the default mailbox.',
                                    )}
                                    href={destroy.url(mailbox.id)}
                                />
                            </div>
                            {mailbox.last_error && (
                                <p className="flex items-start gap-1.5 rounded-md bg-destructive/10 px-2 py-1.5 text-xs text-destructive">
                                    <AlertTriangle className="mt-0.5 size-3.5 shrink-0" />
                                    {mailbox.last_error}
                                </p>
                            )}
                            {mailbox.webhook_url && (
                                <WebhookDetails mailbox={mailbox} />
                            )}
                        </li>
                    ))}
                </ul>
            )}

            <Dialog
                open={editing !== null}
                onOpenChange={(open) => !open && setEditing(null)}
            >
                <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-xl">
                    {editing !== null && (
                        <MailboxForm
                            mailbox={editing === 'new' ? null : editing}
                            drivers={drivers}
                            groups={groups}
                            categories={categories}
                            onDone={() => setEditing(null)}
                        />
                    )}
                </DialogContent>
            </Dialog>
        </>
    );
}

function WebhookDetails({ mailbox }: { mailbox: Mailbox }) {
    const { t } = useTranslation();
    const [, copy] = useClipboard();

    const rows = [
        { label: t('Webhook URL'), value: mailbox.webhook_url ?? '' },
        {
            label:
                mailbox.driver === 'mailgun'
                    ? t('Signing key')
                    : t('Password (basic auth)'),
            value: mailbox.inbound_secret ?? '',
        },
    ];

    return (
        <dl className="grid gap-1 rounded-md bg-muted/50 p-2 text-xs">
            {rows.map((row) => (
                <div key={row.label} className="flex items-center gap-2">
                    <dt className="w-36 shrink-0 text-muted-foreground">
                        {row.label}
                    </dt>
                    <dd className="min-w-0 flex-1 truncate font-mono">
                        {row.value}
                    </dd>
                    <button
                        type="button"
                        aria-label={t('Copy :label', { label: row.label })}
                        onClick={() => void copy(row.value)}
                        className="rounded p-1 text-muted-foreground hover:bg-background hover:text-foreground"
                    >
                        <Copy className="size-3.5" />
                    </button>
                </div>
            ))}
        </dl>
    );
}

function MailboxForm({
    mailbox,
    drivers,
    groups,
    categories,
    onDone,
}: {
    mailbox: Mailbox | null;
    drivers: Option<Driver>[];
    groups: NamedRecord[];
    categories: CategoryNode[];
    onDone: () => void;
}) {
    const { t } = useTranslation();
    const form = useForm({
        name: mailbox?.name ?? '',
        address: mailbox?.address ?? '',
        is_default: mailbox?.is_default ?? false,
        is_active: mailbox?.is_active ?? true,
        default_group_id: mailbox?.default_group_id ?? null,
        default_category_id: mailbox?.default_category_id ?? null,
        driver: mailbox?.driver ?? ('imap' as Driver),
        imap_host: mailbox?.imap_host ?? '',
        imap_port: mailbox?.imap_port ?? 993,
        imap_encryption: mailbox?.imap_encryption ?? 'ssl',
        imap_username: mailbox?.imap_username ?? '',
        imap_password: '',
        imap_folder: mailbox?.imap_folder ?? 'INBOX',
        delete_after_import: mailbox?.delete_after_import ?? false,
        inbound_secret: '',
    });
    const errors = form.errors as Record<string, string | undefined>;
    const categoryOptions = categories.flatMap((category) => [
        { id: category.id, name: category.name },
        ...(category.children ?? []).map((child) => ({
            id: child.id,
            name: `${category.name} › ${child.name}`,
        })),
    ]);

    const submit = (event: React.FormEvent) => {
        event.preventDefault();

        const options = { preserveScroll: true, onSuccess: onDone };

        if (mailbox) {
            form.put(update.url(mailbox.id), options);
        } else {
            form.post(store.url(), options);
        }
    };

    return (
        <form onSubmit={submit} className="space-y-4">
            <DialogTitle>
                {mailbox ? t('Edit mailbox') : t('Add mailbox')}
            </DialogTitle>
            <DialogDescription>
                {t(
                    'New email to this address opens a ticket; replies to our emails are added to the right ticket.',
                )}
            </DialogDescription>

            <div className="grid gap-4 sm:grid-cols-2">
                <TextField
                    id="mailbox-address"
                    label={t('Email address')}
                    value={form.data.address}
                    onChange={(address) => form.setData('address', address)}
                    error={errors.address}
                    placeholder="support@example.com"
                    required
                />
                <TextField
                    id="mailbox-name"
                    label={t('Sender name')}
                    value={form.data.name}
                    onChange={(name) => form.setData('name', name)}
                    error={errors.name}
                    placeholder={t('e.g. Acme Support')}
                    required
                />
            </div>

            <div className="grid gap-4 sm:grid-cols-2">
                <ChoiceSelect
                    label={t('New tickets go to group')}
                    value={form.data.default_group_id}
                    noneLabel={t('Use routing rules')}
                    options={groups}
                    onChange={(id) => form.setData('default_group_id', id)}
                    error={errors.default_group_id}
                />
                <ChoiceSelect
                    label={t('New tickets get category')}
                    value={form.data.default_category_id}
                    noneLabel={t('No category')}
                    options={categoryOptions}
                    onChange={(id) => form.setData('default_category_id', id)}
                    error={errors.default_category_id}
                />
            </div>

            <div className="grid gap-2">
                <Label>{t('How email arrives')}</Label>
                <Select
                    value={form.data.driver}
                    onValueChange={(driver) =>
                        form.setData('driver', driver as Driver)
                    }
                >
                    <SelectTrigger className="w-full">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        {drivers.map((driver) => (
                            <SelectItem key={driver.value} value={driver.value}>
                                {driver.label}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                <InputError message={errors.driver} />
            </div>

            {form.data.driver === 'imap' ? (
                <div className="space-y-4 rounded-lg border p-3">
                    <div className="grid gap-4 sm:grid-cols-[minmax(0,1fr)_6rem_8rem]">
                        <TextField
                            id="imap-host"
                            label={t('IMAP server')}
                            value={form.data.imap_host}
                            onChange={(host) => form.setData('imap_host', host)}
                            error={errors.imap_host}
                            placeholder="imap.gmail.com"
                        />
                        <TextField
                            id="imap-port"
                            label={t('Port')}
                            value={String(form.data.imap_port ?? '')}
                            onChange={(port) =>
                                form.setData('imap_port', Number(port) || 993)
                            }
                            error={errors.imap_port}
                        />
                        <div className="grid gap-2">
                            <Label>{t('Security')}</Label>
                            <Select
                                value={form.data.imap_encryption ?? NONE}
                                onValueChange={(value) =>
                                    form.setData(
                                        'imap_encryption',
                                        value === NONE ? '' : value,
                                    )
                                }
                            >
                                <SelectTrigger className="w-full">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="ssl">SSL/TLS</SelectItem>
                                    <SelectItem value="starttls">
                                        STARTTLS
                                    </SelectItem>
                                    <SelectItem value={NONE}>
                                        {t('None')}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                    </div>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <TextField
                            id="imap-username"
                            label={t('Username')}
                            value={form.data.imap_username}
                            onChange={(username) =>
                                form.setData('imap_username', username)
                            }
                            error={errors.imap_username}
                        />
                        <div className="grid gap-2">
                            <Label htmlFor="imap-password">
                                {t('Password')}
                            </Label>
                            <input
                                id="imap-password"
                                type="password"
                                autoComplete="new-password"
                                value={form.data.imap_password}
                                placeholder={
                                    mailbox?.has_imap_password
                                        ? t(
                                              'Leave empty to keep the current one',
                                          )
                                        : t('App password recommended')
                                }
                                onChange={(event) =>
                                    form.setData(
                                        'imap_password',
                                        event.target.value,
                                    )
                                }
                                className="h-9 rounded-md border bg-transparent px-3 text-sm shadow-xs outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30"
                            />
                            <InputError message={errors.imap_password} />
                        </div>
                    </div>
                    <TextField
                        id="imap-folder"
                        label={t('Folder')}
                        value={form.data.imap_folder}
                        onChange={(folder) =>
                            form.setData('imap_folder', folder)
                        }
                        error={errors.imap_folder}
                    />
                    <label className="flex items-center gap-2 text-sm">
                        <input
                            type="checkbox"
                            checked={form.data.delete_after_import}
                            onChange={(event) =>
                                form.setData(
                                    'delete_after_import',
                                    event.target.checked,
                                )
                            }
                            className="size-4 accent-[var(--primary)]"
                        />
                        {t(
                            'Delete emails from the server after importing (otherwise they are marked as read)',
                        )}
                    </label>
                </div>
            ) : (
                <div className="space-y-2 rounded-lg border p-3 text-sm">
                    <p className="text-muted-foreground">
                        {form.data.driver === 'mailgun'
                            ? t(
                                  'Create a Mailgun route that forwards to the webhook URL shown after saving, and paste your HTTP webhook signing key here.',
                              )
                            : t(
                                  'Point your Postmark inbound webhook to the URL shown after saving, using the generated password for basic auth.',
                              )}
                    </p>
                    <TextField
                        id="inbound-secret"
                        label={
                            form.data.driver === 'mailgun'
                                ? t('Signing key')
                                : t('Password (basic auth)')
                        }
                        value={form.data.inbound_secret}
                        onChange={(secret) =>
                            form.setData('inbound_secret', secret)
                        }
                        error={errors.inbound_secret}
                        placeholder={
                            mailbox
                                ? t('Leave empty to keep the current one')
                                : t('Leave empty to generate one')
                        }
                    />
                </div>
            )}

            <div className="flex flex-col gap-2 text-sm">
                <label className="flex items-center gap-2">
                    <input
                        type="checkbox"
                        checked={form.data.is_default}
                        onChange={(event) =>
                            form.setData('is_default', event.target.checked)
                        }
                        className="size-4 accent-[var(--primary)]"
                    />
                    {t(
                        'Default mailbox (replies to tickets that did not arrive by email go out from it)',
                    )}
                </label>
                <label className="flex items-center gap-2">
                    <input
                        type="checkbox"
                        checked={form.data.is_active}
                        onChange={(event) =>
                            form.setData('is_active', event.target.checked)
                        }
                        className="size-4 accent-[var(--primary)]"
                    />
                    {t('Active')}
                </label>
            </div>

            <div className="flex justify-end">
                <Button type="submit" disabled={form.processing}>
                    {t('Save')}
                </Button>
            </div>
        </form>
    );
}

function ChoiceSelect({
    label,
    value,
    noneLabel,
    options,
    onChange,
    error,
}: {
    label: string;
    value: number | null;
    noneLabel: string;
    options: NamedRecord[];
    onChange: (id: number | null) => void;
    error?: string;
}) {
    return (
        <div className="grid gap-2">
            <Label>{label}</Label>
            <Select
                value={value ? String(value) : NONE}
                onValueChange={(next) =>
                    onChange(next === NONE ? null : Number(next))
                }
            >
                <SelectTrigger className="w-full">
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value={NONE}>{noneLabel}</SelectItem>
                    {options.map((option) => (
                        <SelectItem key={option.id} value={String(option.id)}>
                            {option.name}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
            <InputError message={error} />
        </div>
    );
}

MailboxesIndex.layout = {
    breadcrumbs: [
        { title: 'Admin center', href: '/admin' },
        { title: 'Email', href: index() },
    ],
};
