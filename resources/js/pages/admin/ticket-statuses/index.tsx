import { Head, router, useForm } from '@inertiajs/react';
import { ArrowDown, ArrowUp, Pencil, Plus, Star, Trash2 } from 'lucide-react';
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
import { useTranslation } from '@/hooks/use-translation';
import { statusColors } from '@/lib/tickets';
import { cn } from '@/lib/utils';
import {
    destroy,
    index,
    makeDefault,
    move,
    store,
    update,
} from '@/routes/admin/ticket-statuses';
import type { Option, StatusColor, TicketStatus } from '@/types';

type AdminStatus = {
    id: number;
    /** Null for a default status that shows the category name. */
    name: string | null;
    label: string;
    category: TicketStatus;
    color: StatusColor;
    description: string | null;
    is_default: boolean;
    is_active: boolean;
    tickets_count: number;
};

type Props = {
    statuses: AdminStatus[];
    categories: Option<TicketStatus>[];
    colors: StatusColor[];
};

/**
 * Admin-defined statuses, grouped by the fixed category that drives ticket behavior.
 */
export default function TicketStatusesIndex({
    statuses,
    categories,
    colors,
}: Props) {
    const { t, tChoice } = useTranslation();
    const [editing, setEditing] = useState<AdminStatus | 'new' | null>(null);

    const moveStatus = (status: AdminStatus, direction: 'up' | 'down') =>
        router.post(
            move.url(status.id),
            { direction },
            { preserveScroll: true },
        );

    return (
        <>
            <Head title={t('Statuses')} />
            <AdminPageHeader
                title={t('Statuses')}
                description={t(
                    'Add your own statuses within each category. The category decides how the ticket behaves (SLA, reopening, closing); the status is what agents see and use as board lanes.',
                )}
                actions={
                    <Button onClick={() => setEditing('new')}>
                        <Plus /> {t('New status')}
                    </Button>
                }
            />

            <div className="space-y-6">
                {categories.map((category) => {
                    const inCategory = statuses.filter(
                        (status) => status.category === category.value,
                    );

                    return (
                        <section key={category.value} className="space-y-2">
                            <h2 className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                                {category.label}
                            </h2>
                            <ul className="divide-y overflow-hidden rounded-xl border bg-card shadow-xs">
                                {inCategory.map((status, position) => (
                                    <li
                                        key={status.id}
                                        className={cn(
                                            'flex items-center gap-3 px-4 py-3',
                                            !status.is_active && 'opacity-60',
                                        )}
                                    >
                                        <div className="flex flex-col">
                                            <button
                                                type="button"
                                                aria-label={t('Move up')}
                                                disabled={position === 0}
                                                onClick={() =>
                                                    moveStatus(status, 'up')
                                                }
                                                className="rounded p-0.5 text-muted-foreground hover:bg-accent disabled:opacity-30"
                                            >
                                                <ArrowUp className="size-3.5" />
                                            </button>
                                            <button
                                                type="button"
                                                aria-label={t('Move down')}
                                                disabled={
                                                    position ===
                                                    inCategory.length - 1
                                                }
                                                onClick={() =>
                                                    moveStatus(status, 'down')
                                                }
                                                className="rounded p-0.5 text-muted-foreground hover:bg-accent disabled:opacity-30"
                                            >
                                                <ArrowDown className="size-3.5" />
                                            </button>
                                        </div>
                                        <span
                                            className={cn(
                                                'size-2.5 shrink-0 rounded-full',
                                                statusColors[status.color].dot,
                                            )}
                                        />
                                        <div className="min-w-0 flex-1">
                                            <p className="flex flex-wrap items-center gap-2 font-medium">
                                                {status.label}
                                                {status.is_default && (
                                                    <Badge variant="secondary">
                                                        {t('Default')}
                                                    </Badge>
                                                )}
                                                {!status.is_active && (
                                                    <Badge variant="outline">
                                                        {t('Off')}
                                                    </Badge>
                                                )}
                                            </p>
                                            <p className="truncate text-xs text-muted-foreground">
                                                {status.description
                                                    ? `${status.description} · `
                                                    : ''}
                                                {tChoice(
                                                    ':count ticket|:count tickets',
                                                    status.tickets_count,
                                                )}
                                            </p>
                                        </div>
                                        {!status.is_default && (
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                title={t('Make default')}
                                                onClick={() =>
                                                    router.post(
                                                        makeDefault.url(
                                                            status.id,
                                                        ),
                                                        {},
                                                        {
                                                            preserveScroll: true,
                                                        },
                                                    )
                                                }
                                            >
                                                <Star />
                                            </Button>
                                        )}
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            title={t('Edit')}
                                            onClick={() => setEditing(status)}
                                        >
                                            <Pencil />
                                        </Button>
                                        {!status.is_default && (
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
                                                title={t('Delete “:name”?', {
                                                    name: status.label,
                                                })}
                                                description={t(
                                                    'Its tickets move to the default status of the same category. Workflows that set this status are turned off.',
                                                )}
                                                href={destroy.url(status.id)}
                                            />
                                        )}
                                    </li>
                                ))}
                            </ul>
                        </section>
                    );
                })}
            </div>

            <Dialog
                open={editing !== null}
                onOpenChange={(open) => !open && setEditing(null)}
            >
                <DialogContent className="sm:max-w-lg">
                    {editing !== null && (
                        <StatusForm
                            status={editing === 'new' ? null : editing}
                            categories={categories}
                            colors={colors}
                            onDone={() => setEditing(null)}
                        />
                    )}
                </DialogContent>
            </Dialog>
        </>
    );
}

function StatusForm({
    status,
    categories,
    colors,
    onDone,
}: {
    status: AdminStatus | null;
    categories: Option<TicketStatus>[];
    colors: StatusColor[];
    onDone: () => void;
}) {
    const { t } = useTranslation();
    const form = useForm({
        name: status?.name ?? '',
        category: status?.category ?? ('pending' as TicketStatus),
        color: status?.color ?? ('violet' as StatusColor),
        description: status?.description ?? '',
        is_active: status?.is_active ?? true,
    });
    const categoryLocked =
        status !== null && (status.is_default || status.tickets_count > 0);

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        form.transform((data) => ({
            ...data,
            name: data.name.trim() === '' ? null : data.name,
        }));

        if (status) {
            form.put(update.url(status.id), {
                preserveScroll: true,
                onSuccess: onDone,
            });
        } else {
            form.post(store.url(), { preserveScroll: true, onSuccess: onDone });
        }
    };

    return (
        <form onSubmit={submit} className="space-y-4">
            <DialogTitle>
                {status ? t('Edit status') : t('New status')}
            </DialogTitle>
            <DialogDescription>
                {categoryLocked
                    ? t(
                          'The category of a default status, or of a status in use, cannot be changed.',
                      )
                    : t(
                          'Tickets in this status behave like the category you pick.',
                      )}
            </DialogDescription>

            <TextField
                id="status-name"
                label={t('Name')}
                value={form.data.name}
                onChange={(name) => form.setData('name', name)}
                error={form.errors.name}
                placeholder={
                    status?.is_default
                        ? categories.find(
                              (category) => category.value === status.category,
                          )?.label
                        : t('e.g. Waiting on vendor')
                }
                required={!status?.is_default}
            />

            <div className="grid gap-2">
                <Label>{t('Category')}</Label>
                <Select
                    value={form.data.category}
                    disabled={categoryLocked}
                    onValueChange={(category) =>
                        form.setData('category', category as TicketStatus)
                    }
                >
                    <SelectTrigger className="w-full">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        {categories.map((category) => (
                            <SelectItem
                                key={category.value}
                                value={category.value}
                            >
                                {category.label}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                <InputError message={form.errors.category} />
            </div>

            <div className="grid gap-2">
                <Label>{t('Color')}</Label>
                <div className="flex flex-wrap gap-2">
                    {colors.map((color) => (
                        <button
                            key={color}
                            type="button"
                            aria-label={color}
                            aria-pressed={form.data.color === color}
                            onClick={() => form.setData('color', color)}
                            className={cn(
                                'size-7 rounded-full ring-offset-2 ring-offset-background transition',
                                statusColors[color].dot,
                                form.data.color === color &&
                                    'ring-2 ring-foreground',
                            )}
                        />
                    ))}
                </div>
                <InputError message={form.errors.color} />
            </div>

            <TextField
                id="status-description"
                label={t('Description')}
                value={form.data.description}
                onChange={(description) =>
                    form.setData('description', description)
                }
                error={form.errors.description}
                placeholder={t('When agents should use it (optional)')}
            />

            {!status?.is_default && (
                <label className="flex items-center gap-2 text-sm">
                    <input
                        type="checkbox"
                        checked={form.data.is_active}
                        onChange={(event) =>
                            form.setData('is_active', event.target.checked)
                        }
                        className="size-4 accent-[var(--primary)]"
                    />
                    {t('Agents can pick this status')}
                </label>
            )}
            <InputError message={form.errors.is_active} />

            <div className="flex justify-end">
                <Button type="submit" disabled={form.processing}>
                    {t('Save')}
                </Button>
            </div>
        </form>
    );
}

TicketStatusesIndex.layout = {
    breadcrumbs: [
        { title: 'Admin center', href: '/admin' },
        { title: 'Statuses', href: index() },
    ],
};
