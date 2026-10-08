import { Head, Link, router } from '@inertiajs/react';
import { CalendarClock, Pencil, Plus, Trash2 } from 'lucide-react';
import Heading from '@/components/heading';
import { weekdays } from '@/components/sla/format';
import { Button } from '@/components/ui/button';
import { useTranslation } from '@/hooks/use-translation';
import { cn } from '@/lib/utils';
import {
    create,
    destroy,
    edit,
    index,
} from '@/routes/admin/business-schedules';
import { index as policiesIndex } from '@/routes/admin/sla-policies';
import type { BusinessScheduleListItem } from '@/types/sla';

export default function BusinessSchedulesIndex({
    schedules,
}: {
    schedules: BusinessScheduleListItem[];
}) {
    const { t, tChoice } = useTranslation();

    const remove = (schedule: BusinessScheduleListItem) => {
        const warning =
            schedule.policies_count > 0
                ? ` ${tChoice(
                      ':count SLA policy uses it and will fall back to calendar hours (24/7).|:count SLA policies use it and will fall back to calendar hours (24/7).',
                      schedule.policies_count,
                  )}`
                : '';

        if (
            window.confirm(
                `${t('Delete “:name”?', { name: schedule.name })}${warning}`,
            )
        ) {
            router.delete(destroy.url(schedule.id), { preserveScroll: true });
        }
    };

    return (
        <>
            <Head title={t('Business hours')} />

            <div className="space-y-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title={t('Business hours')}
                        description={t(
                            'Working hours and holidays used to measure SLA targets in business time.',
                        )}
                    />
                    <div className="flex gap-2">
                        <Button asChild variant="outline">
                            <Link href={policiesIndex()}>
                                {t('SLA policies')}
                            </Link>
                        </Button>
                        <Button asChild>
                            <Link href={create()}>
                                <Plus /> {t('New schedule')}
                            </Link>
                        </Button>
                    </div>
                </div>

                {schedules.length === 0 ? (
                    <div className="flex flex-col items-center gap-2 rounded-xl border border-dashed px-6 py-16 text-center">
                        <span className="rounded-full bg-primary/10 p-3 text-primary">
                            <CalendarClock className="size-6" />
                        </span>
                        <p className="font-medium">
                            {t('No business schedules')}
                        </p>
                        <p className="max-w-sm text-sm text-muted-foreground">
                            {t(
                                'Without a schedule, SLA clocks run on calendar time around the clock.',
                            )}
                        </p>
                    </div>
                ) : (
                    <ul className="space-y-3">
                        {schedules.map((schedule) => (
                            <li
                                key={schedule.id}
                                className="rounded-xl border bg-card p-4 shadow-xs"
                            >
                                <div className="flex flex-wrap items-start justify-between gap-3">
                                    <div>
                                        <Link
                                            href={edit(schedule.id)}
                                            className="font-medium hover:text-primary"
                                        >
                                            {schedule.name}
                                        </Link>
                                        <p className="text-xs text-muted-foreground">
                                            {schedule.timezone} ·{' '}
                                            {tChoice(
                                                ':count holiday|:count holidays',
                                                schedule.holidays_count,
                                            )}{' '}
                                            ·{' '}
                                            {tChoice(
                                                'used by :count policy|used by :count policies',
                                                schedule.policies_count,
                                            )}
                                        </p>
                                    </div>
                                    <div className="flex gap-1">
                                        <Button
                                            asChild
                                            variant="ghost"
                                            size="icon"
                                            aria-label={t('Edit :name', {
                                                name: schedule.name,
                                            })}
                                        >
                                            <Link href={edit(schedule.id)}>
                                                <Pencil />
                                            </Link>
                                        </Button>
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            aria-label={t('Delete :name', {
                                                name: schedule.name,
                                            })}
                                            onClick={() => remove(schedule)}
                                        >
                                            <Trash2 />
                                        </Button>
                                    </div>
                                </div>
                                <div className="mt-3 grid grid-cols-7 gap-1.5">
                                    {weekdays.map((day) => {
                                        const intervals =
                                            schedule.hours[day.key] ?? [];

                                        return (
                                            <div
                                                key={day.key}
                                                className={cn(
                                                    'rounded-md border px-1.5 py-1 text-center text-[11px]',
                                                    intervals.length === 0 &&
                                                        'bg-muted/50 text-muted-foreground',
                                                )}
                                            >
                                                <p className="font-medium">
                                                    {day.short}
                                                </p>
                                                {intervals.length === 0 ? (
                                                    <p>{t('Closed')}</p>
                                                ) : (
                                                    intervals.map(
                                                        (interval) => (
                                                            <p
                                                                key={`${interval.start}-${interval.end}`}
                                                                className="tabular-nums"
                                                            >
                                                                {interval.start}
                                                                –{interval.end}
                                                            </p>
                                                        ),
                                                    )
                                                )}
                                            </div>
                                        );
                                    })}
                                </div>
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        </>
    );
}

BusinessSchedulesIndex.layout = {
    breadcrumbs: [
        { title: 'Admin center', href: '/admin' },
        { title: 'Business hours', href: index() },
    ],
};
