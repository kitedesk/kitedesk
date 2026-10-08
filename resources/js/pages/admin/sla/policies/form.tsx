import { Head, Link, setLayoutProps, useForm } from '@inertiajs/react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { ActiveSwitch } from '@/components/sla/active-switch';
import { DurationInput } from '@/components/sla/duration-input';
import { formatMinutes, metricLabels, metrics } from '@/components/sla/format';
import { MultiSelect } from '@/components/sla/multi-select';
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
import { priorityLabel } from '@/lib/tickets';
import { index as schedulesIndex } from '@/routes/admin/business-schedules';
import {
    create,
    edit,
    index,
    store,
    update,
} from '@/routes/admin/sla-policies';
import type { TicketPriority } from '@/types';
import type {
    SlaConditions,
    SlaMetric,
    SlaPolicy,
    SlaPolicyOptions,
    SlaTargets,
} from '@/types/sla';

const CALENDAR = '__calendar';
const priorities: TicketPriority[] = ['urgent', 'high', 'normal', 'low'];

const emptyTargets = (): SlaTargets => ({
    urgent: { first_response: null, next_reply: null, resolution: null },
    high: { first_response: null, next_reply: null, resolution: null },
    normal: { first_response: null, next_reply: null, resolution: null },
    low: { first_response: null, next_reply: null, resolution: null },
});

type FormData = {
    name: string;
    description: string;
    business_schedule_id: number | null;
    is_active: boolean;
    conditions: SlaConditions;
    targets: SlaTargets;
};

export default function SlaPolicyForm({
    policy,
    options,
}: {
    policy: SlaPolicy | null;
    options: SlaPolicyOptions;
}) {
    const { t } = useTranslation();

    setLayoutProps({
        breadcrumbs: [
            { title: 'Admin center', href: '/admin' },
            { title: 'SLA policies', href: index() },
            policy
                ? { title: policy.name, href: edit(policy.id) }
                : { title: 'New policy', href: create() },
        ],
    });

    const form = useForm<FormData>({
        name: policy?.name ?? '',
        description: policy?.description ?? '',
        business_schedule_id: policy?.business_schedule_id ?? null,
        is_active: policy?.is_active ?? true,
        conditions: policy?.conditions ?? {
            priorities: [],
            group_ids: [],
            organization_ids: [],
        },
        targets: { ...emptyTargets(), ...policy?.targets },
    });

    const errors = form.errors as Record<string, string | undefined>;

    const setTarget = (
        priority: TicketPriority,
        metric: SlaMetric,
        minutes: number | null,
    ) =>
        form.setData('targets', {
            ...form.data.targets,
            [priority]: { ...form.data.targets[priority], [metric]: minutes },
        });

    const setConditions = (changes: Partial<SlaConditions>) =>
        form.setData('conditions', { ...form.data.conditions, ...changes });

    const submit = (event: React.FormEvent) => {
        event.preventDefault();

        if (policy) {
            form.put(update.url(policy.id));
        } else {
            form.post(store.url());
        }
    };

    return (
        <>
            <Head
                title={
                    policy
                        ? t('Edit :name', { name: policy.name })
                        : t('New SLA policy')
                }
            />

            <form onSubmit={submit} className="max-w-3xl space-y-8">
                <Heading
                    title={policy ? t('Edit SLA policy') : t('New SLA policy')}
                    description={t(
                        "Targets apply to new tickets and when a ticket's priority or group changes.",
                    )}
                />

                <section className="grid gap-4 sm:grid-cols-2">
                    <div className="grid gap-2 sm:col-span-2">
                        <Label htmlFor="name">{t('Name')}</Label>
                        <Input
                            id="name"
                            value={form.data.name}
                            onChange={(event) =>
                                form.setData('name', event.target.value)
                            }
                            placeholder={t('e.g. Enterprise customers')}
                        />
                        <InputError message={errors.name} />
                    </div>
                    <div className="grid gap-2 sm:col-span-2">
                        <Label htmlFor="description">{t('Description')}</Label>
                        <Input
                            id="description"
                            value={form.data.description}
                            onChange={(event) =>
                                form.setData('description', event.target.value)
                            }
                            placeholder={t('Optional')}
                        />
                        <InputError message={errors.description} />
                    </div>
                    <div className="grid gap-2">
                        <Label>{t('Measure time in')}</Label>
                        <Select
                            value={
                                form.data.business_schedule_id
                                    ? String(form.data.business_schedule_id)
                                    : CALENDAR
                            }
                            onValueChange={(value) =>
                                form.setData(
                                    'business_schedule_id',
                                    value === CALENDAR ? null : Number(value),
                                )
                            }
                        >
                            <SelectTrigger className="w-full">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value={CALENDAR}>
                                    {t('Calendar hours (24/7)')}
                                </SelectItem>
                                {options.schedules.map((schedule) => (
                                    <SelectItem
                                        key={schedule.id}
                                        value={String(schedule.id)}
                                    >
                                        {schedule.name} ({schedule.timezone})
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <p className="text-xs text-muted-foreground">
                            {t('Manage schedules in')}{' '}
                            <Link
                                href={schedulesIndex()}
                                className="text-primary hover:underline"
                            >
                                {t('business hours')}
                            </Link>
                            .
                        </p>
                        <InputError message={errors.business_schedule_id} />
                    </div>
                    <div className="grid content-start gap-2">
                        <Label>{t('Status')}</Label>
                        <div className="flex h-9 items-center gap-2 text-sm">
                            <ActiveSwitch
                                label={t('Active')}
                                checked={form.data.is_active}
                                onChange={(checked) =>
                                    form.setData('is_active', checked)
                                }
                            />
                            {form.data.is_active ? t('Active') : t('Inactive')}
                        </div>
                    </div>
                </section>

                <section className="space-y-4">
                    <div>
                        <h3 className="font-medium">{t('Conditions')}</h3>
                        <p className="text-sm text-muted-foreground">
                            {t(
                                'The policy applies when the ticket matches all of these. Leave a condition empty to match any value.',
                            )}
                        </p>
                    </div>
                    <div className="grid gap-4 sm:grid-cols-3">
                        <div className="grid gap-2">
                            <Label htmlFor="priorities">{t('Priority')}</Label>
                            <MultiSelect
                                id="priorities"
                                options={options.priorities}
                                value={form.data.conditions.priorities}
                                onChange={(value) =>
                                    setConditions({ priorities: value })
                                }
                                anyLabel={t('Any priority')}
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="groups">{t('Group')}</Label>
                            <MultiSelect
                                id="groups"
                                options={options.groups.map((group) => ({
                                    value: group.id,
                                    label: group.name,
                                }))}
                                value={form.data.conditions.group_ids}
                                onChange={(value) =>
                                    setConditions({ group_ids: value })
                                }
                                anyLabel={t('Any group')}
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="organizations">
                                {t('Organization')}
                            </Label>
                            <MultiSelect
                                id="organizations"
                                options={options.organizations.map(
                                    (organization) => ({
                                        value: organization.id,
                                        label: organization.name,
                                    }),
                                )}
                                value={form.data.conditions.organization_ids}
                                onChange={(value) =>
                                    setConditions({ organization_ids: value })
                                }
                                anyLabel={t('Any organization')}
                            />
                        </div>
                    </div>
                </section>

                <section className="space-y-4">
                    <div>
                        <h3 className="font-medium">{t('Targets')}</h3>
                        <p className="text-sm text-muted-foreground">
                            {t(
                                'How quickly each metric must be met, per priority. Leave blank to not track a metric.',
                            )}
                        </p>
                    </div>

                    <div className="overflow-x-auto rounded-xl border">
                        <table className="w-full min-w-[34rem] text-sm">
                            <thead>
                                <tr className="border-b bg-muted/40 text-left text-xs text-muted-foreground">
                                    <th className="px-3 py-2 font-medium">
                                        {t('Priority')}
                                    </th>
                                    {metrics.map((metric) => (
                                        <th
                                            key={metric}
                                            className="px-3 py-2 font-medium"
                                        >
                                            {metricLabels[metric]}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                {priorities.map((priority) => (
                                    <tr
                                        key={priority}
                                        className="border-b last:border-0"
                                    >
                                        <td className="px-3 py-2 font-medium">
                                            {priorityLabel(priority)}
                                        </td>
                                        {metrics.map((metric) => {
                                            const value =
                                                form.data.targets[priority]?.[
                                                    metric
                                                ];

                                            return (
                                                <td
                                                    key={metric}
                                                    className="px-3 py-2 align-top"
                                                >
                                                    <DurationInput
                                                        label={`${priorityLabel(priority)} ${metricLabels[metric]}`}
                                                        value={value}
                                                        invalid={Boolean(
                                                            errors[
                                                                `targets.${priority}.${metric}`
                                                            ],
                                                        )}
                                                        onChange={(minutes) =>
                                                            setTarget(
                                                                priority,
                                                                metric,
                                                                minutes,
                                                            )
                                                        }
                                                    />
                                                    {value ? (
                                                        <p className="mt-1 text-right text-[11px] text-muted-foreground">
                                                            {formatMinutes(
                                                                value,
                                                            )}
                                                        </p>
                                                    ) : null}
                                                </td>
                                            );
                                        })}
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </section>

                <div className="flex justify-end gap-2">
                    <Button asChild variant="ghost">
                        <Link href={index()}>{t('Cancel')}</Link>
                    </Button>
                    <Button type="submit" disabled={form.processing}>
                        {policy ? t('Save policy') : t('Create policy')}
                    </Button>
                </div>
            </form>
        </>
    );
}
