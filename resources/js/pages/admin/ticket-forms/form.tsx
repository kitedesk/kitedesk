import { Head, Link, setLayoutProps, useForm } from '@inertiajs/react';
import { ArrowDown, ArrowUp, Plus, X } from 'lucide-react';
import { AdminPageHeader } from '@/components/admin/page-header';
import { TextField } from '@/components/admin/text-field';
import InputError from '@/components/input-error';
import { ActiveSwitch } from '@/components/sla/active-switch';
import { Button } from '@/components/ui/button';
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
    create,
    edit,
    index,
    store,
    update,
} from '@/routes/admin/ticket-forms';
import type { TicketFieldType } from '@/types';

type AvailableField = {
    id: number;
    key: string;
    label: string;
    type: TicketFieldType;
    is_visible_to_customers: boolean;
};

type FormData = {
    id: number;
    name: string;
    description: string | null;
    is_default: boolean;
    is_active: boolean;
    fields: { id: number; is_required: boolean }[];
};

export default function TicketFormEditor({
    form: ticketForm,
    fields,
}: {
    form: FormData | null;
    fields: AvailableField[];
}) {
    const { t } = useTranslation();
    const form = useForm({
        name: ticketForm?.name ?? '',
        description: ticketForm?.description ?? '',
        is_default: ticketForm?.is_default ?? false,
        is_active: ticketForm?.is_active ?? true,
        fields: ticketForm?.fields ?? [],
    });
    const errors = form.errors as Record<string, string | undefined>;
    const title = ticketForm ? t('Edit form') : t('New form');

    setLayoutProps({
        breadcrumbs: [
            { title: 'Admin center', href: '/admin' },
            { title: 'Forms & fields', href: index() },
            ticketForm
                ? { title: 'Edit form', href: edit(ticketForm.id) }
                : { title: 'New form', href: create() },
        ],
    });

    const fieldById = (id: number) => fields.find((field) => field.id === id);
    const available = fields.filter(
        (field) => !form.data.fields.some((chosen) => chosen.id === field.id),
    );

    const moveField = (index: number, offset: -1 | 1) => {
        const next = [...form.data.fields];
        [next[index], next[index + offset]] = [
            next[index + offset],
            next[index],
        ];
        form.setData('fields', next);
    };

    const submit = (event: React.FormEvent) => {
        event.preventDefault();

        if (ticketForm) {
            form.put(update.url(ticketForm.id));
        } else {
            form.post(store.url());
        }
    };

    return (
        <>
            <Head title={title} />
            <AdminPageHeader
                title={title}
                description={t(
                    'Pick the fields this form asks for, in order. Required fields must be filled in when a ticket is created.',
                )}
            />

            <form onSubmit={submit} className="max-w-2xl space-y-8">
                <section className="space-y-4">
                    <TextField
                        id="form-name"
                        label={t('Name')}
                        value={form.data.name}
                        onChange={(name) => form.setData('name', name)}
                        error={errors.name}
                        required
                    />
                    <TextField
                        id="form-description"
                        label={t('Description')}
                        value={form.data.description}
                        onChange={(description) =>
                            form.setData('description', description)
                        }
                        error={errors.description}
                        multiline
                    />
                    <div className="flex flex-col gap-3 text-sm">
                        <label className="flex items-center gap-3">
                            <ActiveSwitch
                                checked={form.data.is_active}
                                onChange={(checked) =>
                                    form.setData('is_active', checked)
                                }
                                label={t('Active')}
                            />
                            {t('Active')}
                        </label>
                        <label className="flex items-center gap-3">
                            <ActiveSwitch
                                checked={form.data.is_default}
                                onChange={(checked) =>
                                    form.setData('is_default', checked)
                                }
                                label={t('Default form')}
                            />
                            <span>
                                {t('Default form')}
                                <span className="block text-xs text-muted-foreground">
                                    {t(
                                        'Used for tickets without a category, or whose category has no form.',
                                    )}
                                </span>
                            </span>
                        </label>
                    </div>
                </section>

                <section className="space-y-3">
                    <div>
                        <Label>{t('Fields')}</Label>
                        <p className="text-xs text-muted-foreground">
                            {t(
                                'Agent-only fields are never shown to customers, even on this form.',
                            )}
                        </p>
                    </div>

                    {form.data.fields.length === 0 ? (
                        <p className="rounded-lg border border-dashed p-4 text-center text-sm text-muted-foreground">
                            {t('This form has no fields yet.')}
                        </p>
                    ) : (
                        <ul className="divide-y overflow-hidden rounded-xl border bg-card shadow-xs">
                            {form.data.fields.map((chosen, index) => {
                                const field = fieldById(chosen.id);

                                return (
                                    <li
                                        key={chosen.id}
                                        className="flex items-center gap-3 px-3 py-2"
                                    >
                                        <div className="flex flex-col">
                                            <button
                                                type="button"
                                                aria-label={t('Move up')}
                                                disabled={index === 0}
                                                onClick={() =>
                                                    moveField(index, -1)
                                                }
                                                className="rounded p-0.5 text-muted-foreground hover:bg-accent disabled:opacity-30"
                                            >
                                                <ArrowUp className="size-3.5" />
                                            </button>
                                            <button
                                                type="button"
                                                aria-label={t('Move down')}
                                                disabled={
                                                    index ===
                                                    form.data.fields.length - 1
                                                }
                                                onClick={() =>
                                                    moveField(index, 1)
                                                }
                                                className="rounded p-0.5 text-muted-foreground hover:bg-accent disabled:opacity-30"
                                            >
                                                <ArrowDown className="size-3.5" />
                                            </button>
                                        </div>
                                        <div className="min-w-0 flex-1">
                                            <p className="truncate text-sm font-medium">
                                                {field?.label ??
                                                    `#${chosen.id}`}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                <code>{field?.key}</code>
                                                {field &&
                                                    !field.is_visible_to_customers &&
                                                    ` · ${t('agents only')}`}
                                            </p>
                                        </div>
                                        <label className="flex items-center gap-2 text-xs">
                                            <input
                                                type="checkbox"
                                                checked={chosen.is_required}
                                                onChange={(event) =>
                                                    form.setData(
                                                        'fields',
                                                        form.data.fields.map(
                                                            (item) =>
                                                                item.id ===
                                                                chosen.id
                                                                    ? {
                                                                          ...item,
                                                                          is_required:
                                                                              event
                                                                                  .target
                                                                                  .checked,
                                                                      }
                                                                    : item,
                                                        ),
                                                    )
                                                }
                                                className="size-4 accent-[var(--primary)]"
                                            />
                                            {t('Required')}
                                        </label>
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            aria-label={t('Remove :name', {
                                                name: field?.label ?? '',
                                            })}
                                            onClick={() =>
                                                form.setData(
                                                    'fields',
                                                    form.data.fields.filter(
                                                        (item) =>
                                                            item.id !==
                                                            chosen.id,
                                                    ),
                                                )
                                            }
                                        >
                                            <X />
                                        </Button>
                                    </li>
                                );
                            })}
                        </ul>
                    )}

                    {available.length > 0 && (
                        <Select
                            value=""
                            onValueChange={(id) =>
                                form.setData('fields', [
                                    ...form.data.fields,
                                    { id: Number(id), is_required: false },
                                ])
                            }
                        >
                            <SelectTrigger className="w-full sm:w-72">
                                <Plus className="size-4" />
                                <SelectValue placeholder={t('Add a field…')} />
                            </SelectTrigger>
                            <SelectContent>
                                {available.map((field) => (
                                    <SelectItem
                                        key={field.id}
                                        value={String(field.id)}
                                    >
                                        {field.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    )}
                    <InputError message={errors.fields} />
                </section>

                <div className="flex justify-end gap-2">
                    <Button variant="ghost" asChild>
                        <Link href={index()}>{t('Cancel')}</Link>
                    </Button>
                    <Button type="submit" disabled={form.processing}>
                        {t('Save form')}
                    </Button>
                </div>
            </form>
        </>
    );
}
