import { Head, Link, setLayoutProps, useForm } from '@inertiajs/react';
import { Plus, X } from 'lucide-react';
import { useState } from 'react';
import { AdminPageHeader } from '@/components/admin/page-header';
import {
    conditionFields,
    ConditionValueInput,
} from '@/components/admin/routing-conditions';
import type {
    RoutingCondition,
    RoutingOptions,
    RoutingRule,
} from '@/components/admin/routing-conditions';
import InputError from '@/components/input-error';
import { ActiveSwitch } from '@/components/sla/active-switch';
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
import {
    create,
    edit,
    index,
    store,
    update,
} from '@/routes/admin/routing-rules';

const KEEP = '__keep';

export default function RoutingRuleForm({
    rule,
    options,
}: {
    rule: RoutingRule | null;
    options: RoutingOptions;
}) {
    const { t } = useTranslation();
    const fields = conditionFields(options);
    const [tagDraft, setTagDraft] = useState('');
    const form = useForm({
        name: rule?.name ?? '',
        is_active: rule?.is_active ?? true,
        match: rule?.match ?? ('all' as 'all' | 'any'),
        conditions:
            rule?.conditions ??
            ([
                { field: 'category', operator: 'is', value: '' },
            ] as RoutingCondition[]),
        actions: rule?.actions ?? {
            group_id: 0,
            priority: null as string | null,
            tags: [] as string[],
        },
    });
    const errors = form.errors as Record<string, string | undefined>;
    const title = rule ? t('Edit routing rule') : t('New routing rule');

    setLayoutProps({
        breadcrumbs: [
            { title: 'Admin center', href: '/admin' },
            { title: 'Routing rules', href: index() },
            rule
                ? { title: rule.name, href: edit(rule.id) }
                : { title: 'New routing rule', href: create() },
        ],
    });

    const setCondition = (position: number, condition: RoutingCondition) =>
        form.setData(
            'conditions',
            form.data.conditions.map((current, i) =>
                i === position ? condition : current,
            ),
        );

    const operatorsFor = (field: string) =>
        options.operators.filter(
            (operator) =>
                operator.value !== 'contains' ||
                fields.find((definition) => definition.value === field)
                    ?.choices === null,
        );

    const addTag = () => {
        const tag = tagDraft.trim().toLowerCase().replace(/\s+/g, '_');

        if (tag && !form.data.actions.tags.includes(tag)) {
            form.setData('actions', {
                ...form.data.actions,
                tags: [...form.data.actions.tags, tag],
            });
        }

        setTagDraft('');
    };

    const submit = (event: React.FormEvent) => {
        event.preventDefault();

        if (rule) {
            form.put(update.url(rule.id));
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
                    'Rules run when a ticket is created without a group, and again when its category changes.',
                )}
            />

            <form onSubmit={submit} className="max-w-3xl space-y-8">
                <section className="grid gap-4 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-end">
                    <div className="grid gap-2">
                        <Label htmlFor="rule-name">{t('Name')}</Label>
                        <Input
                            id="rule-name"
                            value={form.data.name}
                            onChange={(event) =>
                                form.setData('name', event.target.value)
                            }
                            placeholder={t('e.g. Billing questions')}
                            required
                        />
                        <InputError message={errors.name} />
                    </div>
                    <label className="flex h-9 items-center gap-3 text-sm">
                        <ActiveSwitch
                            checked={form.data.is_active}
                            onChange={(checked) =>
                                form.setData('is_active', checked)
                            }
                            label={t('Active')}
                        />
                        {t('Active')}
                    </label>
                </section>

                <section className="space-y-3">
                    <div className="flex flex-wrap items-center gap-2 text-sm">
                        <span className="font-medium">{t('When')}</span>
                        <Select
                            value={form.data.match}
                            onValueChange={(match) =>
                                form.setData('match', match as 'all' | 'any')
                            }
                        >
                            <SelectTrigger className="h-8 w-auto">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">
                                    {t('all conditions match')}
                                </SelectItem>
                                <SelectItem value="any">
                                    {t('any condition matches')}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    {form.data.conditions.length === 0 && (
                        <p className="rounded-lg border border-dashed p-4 text-center text-sm text-muted-foreground">
                            {t(
                                'No conditions: this rule matches every ticket. Keep it last as a catch-all.',
                            )}
                        </p>
                    )}

                    <ul className="space-y-2">
                        {form.data.conditions.map((condition, position) => {
                            const field = fields.find(
                                (definition) =>
                                    definition.value === condition.field,
                            );

                            return (
                                <li
                                    key={position}
                                    className="grid gap-2 rounded-lg border bg-card p-2 sm:grid-cols-[minmax(0,1fr)_9rem_minmax(0,1fr)_auto]"
                                >
                                    <Select
                                        value={condition.field}
                                        onValueChange={(next) =>
                                            setCondition(position, {
                                                field: next,
                                                operator: 'is',
                                                value: '',
                                            })
                                        }
                                    >
                                        <SelectTrigger
                                            className="w-full"
                                            aria-label={t('Field')}
                                        >
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {fields.map((definition) => (
                                                <SelectItem
                                                    key={definition.value}
                                                    value={definition.value}
                                                >
                                                    {definition.label}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <Select
                                        value={condition.operator}
                                        onValueChange={(operator) =>
                                            setCondition(position, {
                                                ...condition,
                                                operator:
                                                    operator as RoutingCondition['operator'],
                                            })
                                        }
                                    >
                                        <SelectTrigger
                                            className="w-full"
                                            aria-label={t('Operator')}
                                        >
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {operatorsFor(condition.field).map(
                                                (operator) => (
                                                    <SelectItem
                                                        key={operator.value}
                                                        value={operator.value}
                                                    >
                                                        {operator.label}
                                                    </SelectItem>
                                                ),
                                            )}
                                        </SelectContent>
                                    </Select>
                                    <ConditionValueInput
                                        field={field}
                                        value={condition.value}
                                        onChange={(value) =>
                                            setCondition(position, {
                                                ...condition,
                                                value,
                                            })
                                        }
                                    />
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        aria-label={t('Remove condition')}
                                        onClick={() =>
                                            form.setData(
                                                'conditions',
                                                form.data.conditions.filter(
                                                    (_, i) => i !== position,
                                                ),
                                            )
                                        }
                                    >
                                        <X />
                                    </Button>
                                    {(errors[`conditions.${position}.field`] ??
                                        errors[
                                            `conditions.${position}.value`
                                        ]) && (
                                        <p className="text-sm text-destructive sm:col-span-4">
                                            {errors[
                                                `conditions.${position}.field`
                                            ] ??
                                                errors[
                                                    `conditions.${position}.value`
                                                ]}
                                        </p>
                                    )}
                                </li>
                            );
                        })}
                    </ul>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        onClick={() =>
                            form.setData('conditions', [
                                ...form.data.conditions,
                                {
                                    field: 'category',
                                    operator: 'is',
                                    value: '',
                                },
                            ])
                        }
                    >
                        <Plus /> {t('Add condition')}
                    </Button>
                    <InputError message={errors.conditions} />
                </section>

                <section className="space-y-4">
                    <p className="text-sm font-medium">{t('Then')}</p>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label>{t('Assign to group')}</Label>
                            <Select
                                value={
                                    form.data.actions.group_id
                                        ? String(form.data.actions.group_id)
                                        : ''
                                }
                                onValueChange={(id) =>
                                    form.setData('actions', {
                                        ...form.data.actions,
                                        group_id: Number(id),
                                    })
                                }
                            >
                                <SelectTrigger className="w-full">
                                    <SelectValue
                                        placeholder={t('Choose a group…')}
                                    />
                                </SelectTrigger>
                                <SelectContent>
                                    {options.groups.map((group) => (
                                        <SelectItem
                                            key={group.id}
                                            value={String(group.id)}
                                        >
                                            {group.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={errors['actions.group_id']} />
                        </div>
                        <div className="grid gap-2">
                            <Label>{t('Set priority')}</Label>
                            <Select
                                value={form.data.actions.priority ?? KEEP}
                                onValueChange={(priority) =>
                                    form.setData('actions', {
                                        ...form.data.actions,
                                        priority:
                                            priority === KEEP ? null : priority,
                                    })
                                }
                            >
                                <SelectTrigger className="w-full">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value={KEEP}>
                                        {t("Don't change")}
                                    </SelectItem>
                                    {options.priorities.map((priority) => (
                                        <SelectItem
                                            key={priority.value}
                                            value={priority.value}
                                        >
                                            {priority.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <p className="text-xs text-muted-foreground">
                                {t(
                                    'Only applied when nobody chose a priority explicitly.',
                                )}
                            </p>
                        </div>
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="rule-tags">{t('Add tags')}</Label>
                        <div className="flex min-h-9 flex-wrap items-center gap-1 rounded-md border px-1.5 py-1 shadow-xs dark:bg-input/30">
                            {form.data.actions.tags.map((tag) => (
                                <span
                                    key={tag}
                                    className="inline-flex items-center gap-0.5 rounded bg-muted py-0.5 pr-0.5 pl-1.5 text-xs"
                                >
                                    {tag}
                                    <button
                                        type="button"
                                        aria-label={t('Remove :name', {
                                            name: tag,
                                        })}
                                        onClick={() =>
                                            form.setData('actions', {
                                                ...form.data.actions,
                                                tags: form.data.actions.tags.filter(
                                                    (value) => value !== tag,
                                                ),
                                            })
                                        }
                                        className="rounded p-0.5 text-muted-foreground hover:bg-background"
                                    >
                                        <X className="size-3" />
                                    </button>
                                </span>
                            ))}
                            <input
                                id="rule-tags"
                                value={tagDraft}
                                onChange={(event) =>
                                    setTagDraft(event.target.value)
                                }
                                onKeyDown={(event) => {
                                    if (
                                        event.key === 'Enter' ||
                                        event.key === ','
                                    ) {
                                        event.preventDefault();
                                        addTag();
                                    }
                                }}
                                onBlur={() => tagDraft && addTag()}
                                placeholder={
                                    form.data.actions.tags.length
                                        ? ''
                                        : t('Add tags…')
                                }
                                className="min-w-16 flex-1 bg-transparent px-1 text-sm outline-none placeholder:text-muted-foreground"
                            />
                        </div>
                    </div>
                </section>

                <div className="flex justify-end gap-2">
                    <Button variant="ghost" asChild>
                        <Link href={index()}>{t('Cancel')}</Link>
                    </Button>
                    <Button type="submit" disabled={form.processing}>
                        {t('Save rule')}
                    </Button>
                </div>
            </form>
        </>
    );
}
