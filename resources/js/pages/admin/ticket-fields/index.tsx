import { Head, router, useForm } from '@inertiajs/react';
import {
    ArrowDown,
    ArrowUp,
    ListChecks,
    Pencil,
    Plus,
    Trash2,
    X,
} from 'lucide-react';
import { useState } from 'react';
import { ConfirmAction } from '@/components/admin/confirm-action';
import { FormsFieldsTabs } from '@/components/admin/forms-fields-tabs';
import { AdminPageHeader } from '@/components/admin/page-header';
import { TextField } from '@/components/admin/text-field';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useTranslation } from '@/hooks/use-translation';
import {
    destroy,
    index,
    move,
    store,
    update,
} from '@/routes/admin/ticket-fields';
import type { Option, TicketFieldType } from '@/types';

type AdminField = {
    id: number;
    key: string;
    label: string;
    type: TicketFieldType;
    options: string[] | null;
    is_global: boolean;
    is_required: boolean;
    ticket_types: string[] | null;
    is_visible_to_customers: boolean;
    forms_count: number;
};

export default function TicketFieldsIndex({
    fields,
    types,
    ticketTypes,
}: {
    fields: AdminField[];
    types: Option[];
    ticketTypes: Option[];
}) {
    const { t, tChoice } = useTranslation();
    const [editing, setEditing] = useState<AdminField | 'new' | null>(null);

    return (
        <>
            <Head title={t('Ticket fields')} />
            <AdminPageHeader
                title={t('Forms & fields')}
                description={t(
                    'Fields capture extra data on tickets. Show a field on every ticket, or add it to ticket forms to choose where it appears and whether it is required.',
                )}
                actions={
                    <Button onClick={() => setEditing('new')}>
                        <Plus /> {t('New field')}
                    </Button>
                }
            />
            <FormsFieldsTabs active="fields" />

            {fields.length === 0 ? (
                <div className="rounded-xl border border-dashed px-6 py-16 text-center text-sm text-muted-foreground">
                    <ListChecks className="mx-auto mb-2 size-6" />
                    {t('No custom fields yet.')}
                </div>
            ) : (
                <ul className="divide-y overflow-hidden rounded-xl border bg-card shadow-xs">
                    {fields.map((field, position) => (
                        <li
                            key={field.id}
                            className="flex items-center gap-3 px-4 py-3"
                        >
                            <div className="flex flex-col">
                                <button
                                    type="button"
                                    aria-label={t('Move up')}
                                    disabled={position === 0}
                                    onClick={() =>
                                        router.post(
                                            move.url(field.id),
                                            { direction: 'up' },
                                            { preserveScroll: true },
                                        )
                                    }
                                    className="rounded p-0.5 text-muted-foreground hover:bg-accent disabled:opacity-30"
                                >
                                    <ArrowUp className="size-3.5" />
                                </button>
                                <button
                                    type="button"
                                    aria-label={t('Move down')}
                                    disabled={position === fields.length - 1}
                                    onClick={() =>
                                        router.post(
                                            move.url(field.id),
                                            { direction: 'down' },
                                            { preserveScroll: true },
                                        )
                                    }
                                    className="rounded p-0.5 text-muted-foreground hover:bg-accent disabled:opacity-30"
                                >
                                    <ArrowDown className="size-3.5" />
                                </button>
                            </div>
                            <div className="min-w-0 flex-1">
                                <p className="font-medium">{field.label}</p>
                                <p className="truncate text-xs text-muted-foreground">
                                    <code>{field.key}</code> ·{' '}
                                    {
                                        types.find(
                                            (type) => type.value === field.type,
                                        )?.label
                                    }
                                    {field.options?.length
                                        ? ` · ${field.options.join(', ')}`
                                        : ''}
                                    {' · '}
                                    {field.is_visible_to_customers
                                        ? t('visible to customers')
                                        : t('agents only')}
                                    {' · '}
                                    {field.is_global
                                        ? field.is_required
                                            ? t('on every ticket, required')
                                            : t('on every ticket')
                                        : field.forms_count === 0
                                          ? t('not on any form')
                                          : tChoice(
                                                'on :count form|on :count forms',
                                                field.forms_count,
                                            )}
                                    {field.ticket_types?.length
                                        ? ` · ${field.ticket_types
                                              .map(
                                                  (value) =>
                                                      ticketTypes.find(
                                                          (type) =>
                                                              type.value ===
                                                              value,
                                                      )?.label ?? value,
                                              )
                                              .join(', ')}`
                                        : ''}
                                </p>
                            </div>
                            <Button
                                variant="ghost"
                                size="icon"
                                title={t('Edit')}
                                onClick={() => setEditing(field)}
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
                                title={t('Delete “:name”?', {
                                    name: field.label,
                                })}
                                description={t(
                                    'The field disappears from forms. Values already stored on tickets are kept.',
                                )}
                                href={destroy.url(field.id)}
                            />
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
                        <FieldForm
                            field={editing === 'new' ? null : editing}
                            types={types}
                            ticketTypes={ticketTypes}
                            onDone={() => setEditing(null)}
                        />
                    )}
                </DialogContent>
            </Dialog>
        </>
    );
}

function FieldForm({
    field,
    types,
    ticketTypes,
    onDone,
}: {
    field: AdminField | null;
    types: Option[];
    ticketTypes: Option[];
    onDone: () => void;
}) {
    const { t } = useTranslation();
    const form = useForm({
        label: field?.label ?? '',
        key: field?.key ?? '',
        type: field?.type ?? 'text',
        options: field?.options ?? ([] as string[]),
        is_global: field?.is_global ?? false,
        is_required: field?.is_required ?? false,
        ticket_types: field?.ticket_types ?? ([] as string[]),
        is_visible_to_customers: field?.is_visible_to_customers ?? true,
    });
    const errors = form.errors as Record<string, string | undefined>;

    const submit = (event: React.FormEvent) => {
        event.preventDefault();

        if (field) {
            form.transform(({ type: _type, ...data }) => data);
            form.put(update.url(field.id), {
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
                {field ? t('Edit field') : t('New field')}
            </DialogTitle>
            <DialogDescription>
                {field
                    ? t('The field type cannot be changed after creation.')
                    : t('Choose how agents and customers fill this in.')}
            </DialogDescription>

            <div className="grid gap-4 sm:grid-cols-2">
                <TextField
                    id="field-label"
                    label={t('Label')}
                    value={form.data.label}
                    onChange={(label) => form.setData('label', label)}
                    error={errors.label}
                    required
                />
                <TextField
                    id="field-key"
                    label={t('Key')}
                    value={form.data.key}
                    onChange={(key) => form.setData('key', key)}
                    error={errors.key}
                    placeholder={t('auto from label')}
                />
            </div>

            <div className="grid gap-2">
                <Label>{t('Type')}</Label>
                <Select
                    value={form.data.type}
                    onValueChange={(type) =>
                        form.setData('type', type as TicketFieldType)
                    }
                    disabled={field !== null}
                >
                    <SelectTrigger className="w-full">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        {types.map((option) => (
                            <SelectItem key={option.value} value={option.value}>
                                {option.label}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                <InputError message={errors.type} />
            </div>

            {(form.data.type === 'select' ||
                form.data.type === 'multiselect') && (
                <div className="grid gap-2">
                    <Label>{t('Options')}</Label>
                    <ul className="space-y-2">
                        {form.data.options.map((option, index) => (
                            <li key={index} className="flex items-start gap-2">
                                <div className="flex-1">
                                    <Input
                                        aria-label={t('Option :number', {
                                            number: index + 1,
                                        })}
                                        value={option}
                                        onChange={(event) =>
                                            form.setData(
                                                'options',
                                                form.data.options.map(
                                                    (current, i) =>
                                                        i === index
                                                            ? event.target.value
                                                            : current,
                                                ),
                                            )
                                        }
                                    />
                                    <InputError
                                        message={errors[`options.${index}`]}
                                    />
                                </div>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    aria-label={t('Remove :name', {
                                        name: option || t('option'),
                                    })}
                                    onClick={() =>
                                        form.setData(
                                            'options',
                                            form.data.options.filter(
                                                (_, i) => i !== index,
                                            ),
                                        )
                                    }
                                >
                                    <X />
                                </Button>
                            </li>
                        ))}
                    </ul>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        className="justify-self-start"
                        onClick={() =>
                            form.setData('options', [...form.data.options, ''])
                        }
                    >
                        <Plus /> {t('Add option')}
                    </Button>
                    <InputError message={errors.options} />
                </div>
            )}

            <label className="flex items-center gap-2 text-sm">
                <input
                    type="checkbox"
                    checked={form.data.is_visible_to_customers}
                    onChange={(event) =>
                        form.setData(
                            'is_visible_to_customers',
                            event.target.checked,
                        )
                    }
                    className="size-4 accent-[var(--primary)]"
                />
                {t('Visible to customers on the request form')}
            </label>

            <div className="space-y-2">
                <label className="flex items-center gap-2 text-sm">
                    <input
                        type="checkbox"
                        checked={form.data.is_global}
                        onChange={(event) =>
                            form.setData('is_global', event.target.checked)
                        }
                        className="size-4 accent-[var(--primary)]"
                    />
                    {t('Show on every ticket, whatever its form')}
                </label>
                {form.data.is_global && (
                    <label className="ml-6 flex items-center gap-2 text-sm">
                        <input
                            type="checkbox"
                            checked={form.data.is_required}
                            onChange={(event) =>
                                form.setData(
                                    'is_required',
                                    event.target.checked,
                                )
                            }
                            className="size-4 accent-[var(--primary)]"
                        />
                        {t('Required (on new tickets and before solving)')}
                    </label>
                )}
                <InputError message={errors.is_global ?? errors.is_required} />
            </div>

            <fieldset className="grid gap-2">
                <legend className="mb-2 text-sm font-medium">
                    {t('Ticket types')}
                </legend>
                <div className="flex flex-wrap gap-x-4 gap-y-2">
                    {ticketTypes.map((type) => (
                        <label
                            key={type.value}
                            className="flex items-center gap-2 text-sm"
                        >
                            <input
                                type="checkbox"
                                checked={form.data.ticket_types.includes(
                                    type.value,
                                )}
                                onChange={(event) =>
                                    form.setData(
                                        'ticket_types',
                                        event.target.checked
                                            ? [
                                                  ...form.data.ticket_types,
                                                  type.value,
                                              ]
                                            : form.data.ticket_types.filter(
                                                  (value) =>
                                                      value !== type.value,
                                              ),
                                    )
                                }
                                className="size-4 accent-[var(--primary)]"
                            />
                            {type.label}
                        </label>
                    ))}
                </div>
                <p className="text-xs text-muted-foreground">
                    {t(
                        "Leave all unchecked to show the field on every type. Fields limited to some types never show on the customer request form, since customers don't pick a type.",
                    )}
                </p>
                <InputError message={errors.ticket_types} />
            </fieldset>

            <div className="flex justify-end">
                <Button type="submit" disabled={form.processing}>
                    {t('Save')}
                </Button>
            </div>
        </form>
    );
}

TicketFieldsIndex.layout = {
    breadcrumbs: [
        { title: 'Admin center', href: '/admin' },
        { title: 'Forms & fields', href: '/admin/ticket-forms' },
        { title: 'Fields', href: index() },
    ],
};
