import { Head, useForm } from '@inertiajs/react';
import { UserPlus, X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { CategorySelect } from '@/components/tickets/category-select';
import {
    fieldsForCategory,
    TicketFormFields,
} from '@/components/tickets/form-fields';
import type { CustomFieldValue } from '@/components/tickets/form-fields';
import { RichTextEditor } from '@/components/tickets/rich-text-editor';
import { UserAvatar } from '@/components/tickets/user-avatar';
import { Button } from '@/components/ui/button';
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
import { search } from '@/routes/agent';
import { create, index, store } from '@/routes/agent/tickets';
import type { FormFields, TicketOptions } from '@/types';

type Requester = { id: number; name: string; email: string };

type Props = {
    options: TicketOptions;
    forms: FormFields;
    defaultFormId: number | null;
    requester: Requester | null;
};

const NONE = '__none';

export default function CreateTicket({
    options,
    forms,
    defaultFormId,
    requester,
}: Props) {
    const { t } = useTranslation();
    const [selectedRequester, setSelectedRequester] =
        useState<Requester | null>(requester);
    const [isNewRequester, setIsNewRequester] = useState(false);

    const form = useForm({
        requester_id: requester?.id ?? (null as number | null),
        requester_name: '',
        requester_email: '',
        subject: '',
        body: '',
        priority: 'normal',
        type: null as string | null,
        assignee_id: null as number | null,
        group_id: null as number | null,
        category_id: null as number | null,
        custom_fields: {} as Record<string, CustomFieldValue>,
    });
    const fields = fieldsForCategory(
        options.categories,
        forms,
        form.data.category_id,
        defaultFormId,
    );

    const submit = (event: React.FormEvent) => {
        event.preventDefault();

        // Only send the fields of the form that applies to the chosen category.
        const keys = fields.map((field) => field.key);
        form.transform((data) => ({
            ...data,
            custom_fields: Object.fromEntries(
                Object.entries(data.custom_fields).filter(([key]) =>
                    keys.includes(key),
                ),
            ),
        }));

        form.post(store.url());
    };

    return (
        <>
            <Head title={t('New ticket')} />

            <form
                onSubmit={submit}
                className="mx-auto w-full max-w-4xl space-y-8 p-4 md:p-8"
            >
                <Heading
                    title={t('New ticket')}
                    description={t(
                        "Log a request on behalf of a customer — they'll get your replies by email.",
                    )}
                />

                <section className="grid gap-2">
                    <Label>{t('Requester')}</Label>
                    {selectedRequester ? (
                        <div className="flex items-center gap-3 rounded-lg border bg-card p-3">
                            <UserAvatar name={selectedRequester.name} />
                            <div className="min-w-0 flex-1">
                                <p className="truncate text-sm font-medium">
                                    {selectedRequester.name}
                                </p>
                                <p className="truncate text-xs text-muted-foreground">
                                    {selectedRequester.email}
                                </p>
                            </div>
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                aria-label={t('Change requester')}
                                onClick={() => {
                                    setSelectedRequester(null);
                                    form.setData('requester_id', null);
                                }}
                            >
                                <X />
                            </Button>
                        </div>
                    ) : isNewRequester ? (
                        <div className="grid gap-3 rounded-lg border bg-card p-3 sm:grid-cols-2">
                            <div className="grid gap-1.5">
                                <Label
                                    htmlFor="requester_name"
                                    className="text-xs"
                                >
                                    {t('Name')}
                                </Label>
                                <Input
                                    id="requester_name"
                                    value={form.data.requester_name}
                                    onChange={(event) =>
                                        form.setData(
                                            'requester_name',
                                            event.target.value,
                                        )
                                    }
                                />
                                <InputError
                                    message={form.errors.requester_name}
                                />
                            </div>
                            <div className="grid gap-1.5">
                                <Label
                                    htmlFor="requester_email"
                                    className="text-xs"
                                >
                                    {t('Email')}
                                </Label>
                                <Input
                                    id="requester_email"
                                    type="email"
                                    value={form.data.requester_email}
                                    onChange={(event) =>
                                        form.setData(
                                            'requester_email',
                                            event.target.value,
                                        )
                                    }
                                />
                                <InputError
                                    message={form.errors.requester_email}
                                />
                            </div>
                            <button
                                type="button"
                                onClick={() => setIsNewRequester(false)}
                                className="justify-self-start text-xs text-muted-foreground hover:text-foreground"
                            >
                                {t('Search existing customers instead')}
                            </button>
                        </div>
                    ) : (
                        <RequesterSearch
                            onSelect={(user) => {
                                setSelectedRequester(user);
                                form.setData('requester_id', user.id);
                            }}
                            onCreateNew={() => setIsNewRequester(true)}
                        />
                    )}
                    <InputError message={form.errors.requester_id} />
                </section>

                <div className="grid gap-8 lg:grid-cols-[minmax(0,1fr)_16rem]">
                    <section className="space-y-4">
                        {options.categories.length > 0 && (
                            <CategorySelect
                                categories={options.categories}
                                value={form.data.category_id}
                                onChange={(id) =>
                                    form.setData('category_id', id)
                                }
                                allowNone
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
                                placeholder={t('Short summary of the request')}
                            />
                            <InputError message={form.errors.subject} />
                        </div>
                        <div className="grid gap-2">
                            <Label>{t('Description')}</Label>
                            <RichTextEditor
                                value={form.data.body}
                                onChange={(html) => form.setData('body', html)}
                                placeholder={t('Describe the request…')}
                                minHeight="12rem"
                            />
                            <InputError message={form.errors.body} />
                        </div>
                    </section>

                    <aside className="space-y-4">
                        <PropertySelect
                            label={t('Priority')}
                            value={form.data.priority}
                            options={options.priorities}
                            onChange={(value) =>
                                form.setData('priority', value ?? 'normal')
                            }
                        />
                        <PropertySelect
                            label={t('Type')}
                            value={form.data.type}
                            options={options.types}
                            allowNone
                            onChange={(value) => form.setData('type', value)}
                        />
                        <PropertySelect
                            label={t('Assignee')}
                            value={
                                form.data.assignee_id
                                    ? String(form.data.assignee_id)
                                    : null
                            }
                            options={options.agents.map((agent) => ({
                                value: String(agent.id),
                                label: agent.name,
                            }))}
                            allowNone
                            onChange={(value) =>
                                form.setData(
                                    'assignee_id',
                                    value ? Number(value) : null,
                                )
                            }
                        />
                        <PropertySelect
                            label={t('Group')}
                            hint={t(
                                'Leave empty to let routing rules pick the group.',
                            )}
                            value={
                                form.data.group_id
                                    ? String(form.data.group_id)
                                    : null
                            }
                            options={options.groups.map((group) => ({
                                value: String(group.id),
                                label: group.name,
                            }))}
                            allowNone
                            onChange={(value) =>
                                form.setData(
                                    'group_id',
                                    value ? Number(value) : null,
                                )
                            }
                        />
                        <TicketFormFields
                            fields={fields}
                            values={form.data.custom_fields}
                            errors={
                                form.errors as Record<
                                    string,
                                    string | undefined
                                >
                            }
                            onChange={(key, value) =>
                                form.setData('custom_fields', {
                                    ...form.data.custom_fields,
                                    [key]: value,
                                })
                            }
                        />
                    </aside>
                </div>

                <div className="flex justify-end gap-2">
                    <Button
                        type="button"
                        variant="ghost"
                        onClick={() => window.history.back()}
                    >
                        {t('Cancel')}
                    </Button>
                    <Button type="submit" disabled={form.processing}>
                        {t('Create ticket')}
                    </Button>
                </div>
            </form>
        </>
    );
}

CreateTicket.layout = {
    breadcrumbs: [
        { title: 'Tickets', href: index() },
        { title: 'New ticket', href: create() },
    ],
};

function PropertySelect({
    label,
    value,
    options,
    onChange,
    allowNone = false,
    hint,
}: {
    label: string;
    hint?: string;
    value: string | null;
    options: { value: string; label: string }[];
    onChange: (value: string | null) => void;
    allowNone?: boolean;
}) {
    return (
        <div className="grid gap-1.5">
            <Label className="text-xs text-muted-foreground">{label}</Label>
            <Select
                value={value ?? NONE}
                onValueChange={(next) => onChange(next === NONE ? null : next)}
            >
                <SelectTrigger className="w-full">
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    {allowNone && <SelectItem value={NONE}>—</SelectItem>}
                    {options.map((option) => (
                        <SelectItem key={option.value} value={option.value}>
                            {option.label}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
            {hint && <p className="text-xs text-muted-foreground">{hint}</p>}
        </div>
    );
}

function RequesterSearch({
    onSelect,
    onCreateNew,
}: {
    onSelect: (user: Requester) => void;
    onCreateNew: () => void;
}) {
    const { t } = useTranslation();
    const [query, setQuery] = useState('');
    const [results, setResults] = useState<Requester[]>([]);
    const timeout = useRef<number | undefined>(undefined);

    useEffect(() => {
        window.clearTimeout(timeout.current);

        if (query.trim().length < 2) {
            setResults([]);

            return;
        }

        timeout.current = window.setTimeout(async () => {
            const response = await fetch(
                search.url({ query: { q: query, customers_only: 1 } }),
                {
                    headers: { Accept: 'application/json' },
                },
            );

            if (response.ok) {
                const payload = (await response.json()) as {
                    users: Requester[];
                };
                setResults(payload.users);
            }
        }, 200);
    }, [query]);

    return (
        <div className="relative">
            <Input
                value={query}
                onChange={(event) => setQuery(event.target.value)}
                placeholder={t('Search customers by name or email…')}
                autoFocus
            />
            {query.trim().length >= 2 && (
                <div className="absolute z-20 mt-1 w-full overflow-hidden rounded-lg border bg-popover shadow-lg">
                    {results.map((user) => (
                        <button
                            key={user.id}
                            type="button"
                            onClick={() => onSelect(user)}
                            className="flex w-full items-center gap-3 px-3 py-2 text-left text-sm hover:bg-accent"
                        >
                            <UserAvatar name={user.name} className="size-6" />
                            <span className="truncate">{user.name}</span>
                            <span className="truncate text-xs text-muted-foreground">
                                {user.email}
                            </span>
                        </button>
                    ))}
                    <button
                        type="button"
                        onClick={onCreateNew}
                        className="flex w-full items-center gap-2 border-t px-3 py-2 text-left text-sm text-primary hover:bg-accent"
                    >
                        <UserPlus className="size-4" />{' '}
                        {t('Add a new customer')}
                    </button>
                </div>
            )}
        </div>
    );
}
