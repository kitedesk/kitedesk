import { Head, Link, router } from '@inertiajs/react';
import {
    ArrowDown,
    ArrowUp,
    CalendarClock,
    Info,
    Pencil,
    Plus,
    Timer,
    Trash2,
} from 'lucide-react';
import Heading from '@/components/heading';
import { ActiveSwitch } from '@/components/sla/active-switch';
import { formatMinutes, metricLabels, metrics } from '@/components/sla/format';
import { Button } from '@/components/ui/button';
import { useTranslation } from '@/hooks/use-translation';
import { priorityLabel } from '@/lib/tickets';
import { cn } from '@/lib/utils';
import { index as schedulesIndex } from '@/routes/admin/business-schedules';
import {
    create,
    destroy,
    edit,
    index,
    move,
    toggle,
} from '@/routes/admin/sla-policies';
import type { TicketPriority } from '@/types';
import type { SlaPolicyListItem } from '@/types/sla';

const priorities: TicketPriority[] = ['urgent', 'high', 'normal', 'low'];

export default function SlaPoliciesIndex({
    policies,
    schedulesCount,
}: {
    policies: SlaPolicyListItem[];
    schedulesCount: number;
}) {
    const { t } = useTranslation();

    const remove = (policy: SlaPolicyListItem) => {
        if (
            window.confirm(
                t('Delete the “:name” SLA policy?', { name: policy.name }),
            )
        ) {
            router.delete(destroy.url(policy.id), { preserveScroll: true });
        }
    };

    return (
        <>
            <Head title={t('SLA policies')} />

            <div className="space-y-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title={t('SLA policies')}
                        description={t(
                            'Service level targets for replying to and resolving tickets.',
                        )}
                    />
                    <div className="flex gap-2">
                        <Button asChild variant="outline">
                            <Link href={schedulesIndex()}>
                                <CalendarClock /> {t('Business hours')}
                                <span className="text-xs text-muted-foreground">
                                    ({schedulesCount})
                                </span>
                            </Link>
                        </Button>
                        <Button asChild>
                            <Link href={create()}>
                                <Plus /> {t('New policy')}
                            </Link>
                        </Button>
                    </div>
                </div>

                <div className="flex gap-3 rounded-lg border border-primary/20 bg-primary/5 p-3 text-sm">
                    <Info className="mt-0.5 size-4 shrink-0 text-primary" />
                    <div className="space-y-1">
                        <p>
                            {t(
                                'Policies are checked top to bottom and the first active policy whose conditions match a ticket is applied. Put your most specific policies first.',
                            )}
                        </p>
                        <p className="text-muted-foreground">
                            {t(
                                "Changes apply to new tickets and whenever a ticket's priority or group changes — due dates already set on existing tickets are not recalculated.",
                            )}
                        </p>
                    </div>
                </div>

                {policies.length === 0 ? (
                    <div className="flex flex-col items-center gap-2 rounded-xl border border-dashed px-6 py-16 text-center">
                        <span className="rounded-full bg-primary/10 p-3 text-primary">
                            <Timer className="size-6" />
                        </span>
                        <p className="font-medium">
                            {t('No SLA policies yet')}
                        </p>
                        <p className="max-w-sm text-sm text-muted-foreground">
                            {t(
                                'Create a policy to start tracking first reply and resolution targets on tickets.',
                            )}
                        </p>
                    </div>
                ) : (
                    <ol className="space-y-3">
                        {policies.map((policy, position) => (
                            <li
                                key={policy.id}
                                className={cn(
                                    'rounded-xl border bg-card p-4 shadow-xs',
                                    !policy.is_active && 'opacity-60',
                                )}
                            >
                                <div className="flex flex-wrap items-start gap-3">
                                    <span className="flex size-7 shrink-0 items-center justify-center rounded-full bg-muted text-xs font-semibold tabular-nums">
                                        {position + 1}
                                    </span>
                                    <div className="min-w-0 flex-1">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <Link
                                                href={edit(policy.id)}
                                                className="font-medium hover:text-primary"
                                            >
                                                {policy.name}
                                            </Link>
                                            {!policy.is_active && (
                                                <span className="rounded bg-muted px-1.5 py-0.5 text-[11px] text-muted-foreground">
                                                    {t('Inactive')}
                                                </span>
                                            )}
                                        </div>
                                        {policy.description && (
                                            <p className="text-sm text-muted-foreground">
                                                {policy.description}
                                            </p>
                                        )}
                                        <ConditionSummary policy={policy} />
                                    </div>
                                    <div className="flex items-center gap-1">
                                        <ActiveSwitch
                                            label={
                                                policy.is_active
                                                    ? t('Deactivate :name', {
                                                          name: policy.name,
                                                      })
                                                    : t('Activate :name', {
                                                          name: policy.name,
                                                      })
                                            }
                                            checked={policy.is_active}
                                            onChange={() =>
                                                router.patch(
                                                    toggle.url(policy.id),
                                                    {},
                                                    { preserveScroll: true },
                                                )
                                            }
                                        />
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            aria-label={t('Move up')}
                                            disabled={position === 0}
                                            onClick={() =>
                                                router.patch(
                                                    move.url(policy.id),
                                                    { direction: 'up' },
                                                    { preserveScroll: true },
                                                )
                                            }
                                        >
                                            <ArrowUp />
                                        </Button>
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            aria-label={t('Move down')}
                                            disabled={
                                                position === policies.length - 1
                                            }
                                            onClick={() =>
                                                router.patch(
                                                    move.url(policy.id),
                                                    { direction: 'down' },
                                                    { preserveScroll: true },
                                                )
                                            }
                                        >
                                            <ArrowDown />
                                        </Button>
                                        <Button
                                            asChild
                                            variant="ghost"
                                            size="icon"
                                            aria-label={t('Edit :name', {
                                                name: policy.name,
                                            })}
                                        >
                                            <Link href={edit(policy.id)}>
                                                <Pencil />
                                            </Link>
                                        </Button>
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            aria-label={t('Delete :name', {
                                                name: policy.name,
                                            })}
                                            onClick={() => remove(policy)}
                                        >
                                            <Trash2 />
                                        </Button>
                                    </div>
                                </div>

                                <div className="mt-4 overflow-x-auto">
                                    <table className="w-full min-w-[28rem] text-xs">
                                        <thead>
                                            <tr className="text-left text-muted-foreground">
                                                <th className="py-1 pr-3 font-medium">
                                                    {t('Priority')}
                                                </th>
                                                {metrics.map((metric) => (
                                                    <th
                                                        key={metric}
                                                        className="px-3 py-1 font-medium"
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
                                                    className="border-t"
                                                >
                                                    <td className="py-1.5 pr-3 font-medium">
                                                        {priorityLabel(
                                                            priority,
                                                        )}
                                                    </td>
                                                    {metrics.map((metric) => (
                                                        <td
                                                            key={metric}
                                                            className="px-3 py-1.5 tabular-nums"
                                                        >
                                                            {formatMinutes(
                                                                policy.targets[
                                                                    priority
                                                                ]?.[metric],
                                                            )}
                                                        </td>
                                                    ))}
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            </li>
                        ))}
                    </ol>
                )}
            </div>
        </>
    );
}

SlaPoliciesIndex.layout = {
    breadcrumbs: [
        { title: 'Admin center', href: '/admin' },
        { title: 'SLA policies', href: index() },
    ],
};

function ConditionSummary({ policy }: { policy: SlaPolicyListItem }) {
    const { t } = useTranslation();
    const or = ` ${t('or')} `;
    const {
        priorities: priorityLabelsList,
        groups,
        organizations,
    } = policy.condition_labels;
    const parts = [
        priorityLabelsList.length > 0 &&
            t('Priority is :values', { values: priorityLabelsList.join(or) }),
        groups.length > 0 && t('Group is :values', { values: groups.join(or) }),
        organizations.length > 0 &&
            t('Organization is :values', { values: organizations.join(or) }),
    ].filter(Boolean);

    return (
        <p className="mt-1.5 flex flex-wrap gap-x-3 gap-y-1 text-xs text-muted-foreground">
            <span>
                {parts.length > 0
                    ? t('Applies when: :conditions', {
                          conditions: parts.join(` ${t('and')} `),
                      })
                    : t('Applies to all tickets')}
            </span>
            <span>
                ·{' '}
                {policy.business_schedule
                    ? t('Measured in :schedule', {
                          schedule: policy.business_schedule.name,
                      })
                    : t('Measured in calendar hours (24/7)')}
            </span>
        </p>
    );
}
