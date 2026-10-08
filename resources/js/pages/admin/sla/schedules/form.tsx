import { Head, Link, router, setLayoutProps, useForm } from '@inertiajs/react';
import { CalendarOff, Copy, Plus, Trash2, X } from 'lucide-react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { weekdays } from '@/components/sla/format';
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
} from '@/routes/admin/business-schedules';
import {
    destroy as destroyHoliday,
    store as storeHoliday,
} from '@/routes/admin/business-schedules/holidays';
import type { BusinessSchedule, WeeklyHours } from '@/types/sla';

type Props = {
    schedule: BusinessSchedule | null;
    timezones: string[];
    defaultTimezone: string;
};

const emptyWeek = (): WeeklyHours =>
    Object.fromEntries(
        weekdays.map((day) => [
            day.key,
            Number(day.key) <= 5 ? [{ start: '09:00', end: '17:00' }] : [],
        ]),
    );

/**
 * Suggest an interval that starts where the day's last interval ends.
 */
function nextInterval(
    intervals: WeeklyHours[string],
): WeeklyHours[string][number] {
    const lastEnd = intervals[intervals.length - 1]?.end;

    return lastEnd && lastEnd < '23:00'
        ? { start: lastEnd, end: '24:00' }
        : { start: '09:00', end: '17:00' };
}

export default function BusinessScheduleForm({
    schedule,
    timezones,
    defaultTimezone,
}: Props) {
    const { t } = useTranslation();

    setLayoutProps({
        breadcrumbs: [
            { title: 'Admin center', href: '/admin' },
            { title: 'Business hours', href: index() },
            schedule
                ? { title: schedule.name, href: edit(schedule.id) }
                : { title: 'New schedule', href: create() },
        ],
    });

    const form = useForm<{
        name: string;
        timezone: string;
        hours: WeeklyHours;
    }>({
        name: schedule?.name ?? '',
        timezone: schedule?.timezone ?? defaultTimezone,
        hours: schedule?.hours ?? emptyWeek(),
    });

    const errors = form.errors as Record<string, string | undefined>;

    const setDay = (day: string, intervals: WeeklyHours[string]) =>
        form.setData('hours', { ...form.data.hours, [day]: intervals });

    const copyToWeekdays = (day: string) =>
        form.setData('hours', {
            ...form.data.hours,
            ...Object.fromEntries(
                ['1', '2', '3', '4', '5'].map((key) => [
                    key,
                    form.data.hours[day].map((interval) => ({ ...interval })),
                ]),
            ),
        });

    const submit = (event: React.FormEvent) => {
        event.preventDefault();

        if (schedule) {
            form.put(update.url(schedule.id), { preserveScroll: true });
        } else {
            form.post(store.url());
        }
    };

    return (
        <>
            <Head
                title={
                    schedule
                        ? t('Edit :name', { name: schedule.name })
                        : t('New business schedule')
                }
            />

            <div className="max-w-3xl space-y-10">
                <form onSubmit={submit} className="space-y-8">
                    <Heading
                        title={
                            schedule
                                ? t('Edit business hours')
                                : t('New business hours')
                        }
                        description={t(
                            'SLA policies using this schedule only count time inside these hours. Changes apply to new due dates; existing ones are not recalculated.',
                        )}
                    />

                    <section className="grid gap-4 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="name">{t('Name')}</Label>
                            <Input
                                id="name"
                                value={form.data.name}
                                onChange={(event) =>
                                    form.setData('name', event.target.value)
                                }
                                placeholder={t('e.g. EMEA support hours')}
                            />
                            <InputError message={errors.name} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="timezone">{t('Timezone')}</Label>
                            <Select
                                value={form.data.timezone}
                                onValueChange={(value) =>
                                    form.setData('timezone', value)
                                }
                            >
                                <SelectTrigger id="timezone" className="w-full">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent className="max-h-80">
                                    {timezones.map((timezone) => (
                                        <SelectItem
                                            key={timezone}
                                            value={timezone}
                                        >
                                            {timezone}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={errors.timezone} />
                        </div>
                    </section>

                    <section className="space-y-3">
                        <div>
                            <h3 className="font-medium">{t('Weekly hours')}</h3>
                            <p className="text-sm text-muted-foreground">
                                {t(
                                    'Add one or more intervals per day (e.g. split for lunch). Use 24:00 to work until midnight.',
                                )}
                            </p>
                        </div>

                        <div className="divide-y rounded-xl border">
                            {weekdays.map((day) => {
                                const intervals =
                                    form.data.hours[day.key] ?? [];

                                return (
                                    <div
                                        key={day.key}
                                        className="flex flex-col gap-2 p-3 sm:flex-row sm:items-start"
                                    >
                                        <div className="w-28 shrink-0 pt-1.5 text-sm font-medium">
                                            {day.label}
                                        </div>
                                        <div className="flex-1 space-y-2">
                                            {intervals.length === 0 && (
                                                <p className="pt-1.5 text-sm text-muted-foreground">
                                                    {t('Closed')}
                                                </p>
                                            )}
                                            {intervals.map(
                                                (interval, position) => (
                                                    <div
                                                        key={position}
                                                        className="space-y-1"
                                                    >
                                                        <div className="flex items-center gap-2">
                                                            <Input
                                                                type="time"
                                                                aria-label={t(
                                                                    ':day interval :number start',
                                                                    {
                                                                        day: day.label,
                                                                        number:
                                                                            position +
                                                                            1,
                                                                    },
                                                                )}
                                                                value={
                                                                    interval.start
                                                                }
                                                                onChange={(
                                                                    event,
                                                                ) =>
                                                                    setDay(
                                                                        day.key,
                                                                        intervals.map(
                                                                            (
                                                                                item,
                                                                                i,
                                                                            ) =>
                                                                                i ===
                                                                                position
                                                                                    ? {
                                                                                          ...item,
                                                                                          start: event
                                                                                              .target
                                                                                              .value,
                                                                                      }
                                                                                    : item,
                                                                        ),
                                                                    )
                                                                }
                                                                className="w-32"
                                                            />
                                                            <span className="text-muted-foreground">
                                                                –
                                                            </span>
                                                            <Input
                                                                aria-label={t(
                                                                    ':day interval :number end',
                                                                    {
                                                                        day: day.label,
                                                                        number:
                                                                            position +
                                                                            1,
                                                                    },
                                                                )}
                                                                value={
                                                                    interval.end
                                                                }
                                                                placeholder="17:00"
                                                                pattern="([01]\d|2[0-3]):[0-5]\d|24:00"
                                                                onChange={(
                                                                    event,
                                                                ) =>
                                                                    setDay(
                                                                        day.key,
                                                                        intervals.map(
                                                                            (
                                                                                item,
                                                                                i,
                                                                            ) =>
                                                                                i ===
                                                                                position
                                                                                    ? {
                                                                                          ...item,
                                                                                          end: event
                                                                                              .target
                                                                                              .value,
                                                                                      }
                                                                                    : item,
                                                                        ),
                                                                    )
                                                                }
                                                                className="w-32 tabular-nums"
                                                            />
                                                            <Button
                                                                type="button"
                                                                variant="ghost"
                                                                size="icon"
                                                                aria-label={t(
                                                                    'Remove interval',
                                                                )}
                                                                onClick={() =>
                                                                    setDay(
                                                                        day.key,
                                                                        intervals.filter(
                                                                            (
                                                                                _,
                                                                                i,
                                                                            ) =>
                                                                                i !==
                                                                                position,
                                                                        ),
                                                                    )
                                                                }
                                                            >
                                                                <X />
                                                            </Button>
                                                        </div>
                                                        <InputError
                                                            message={
                                                                errors[
                                                                    `hours.${day.key}.${position}.start`
                                                                ]
                                                            }
                                                        />
                                                        <InputError
                                                            message={
                                                                errors[
                                                                    `hours.${day.key}.${position}.end`
                                                                ]
                                                            }
                                                        />
                                                    </div>
                                                ),
                                            )}
                                        </div>
                                        <div className="flex gap-1">
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="sm"
                                                onClick={() =>
                                                    setDay(day.key, [
                                                        ...intervals,
                                                        nextInterval(intervals),
                                                    ])
                                                }
                                            >
                                                <Plus /> {t('Add')}
                                            </Button>
                                            {intervals.length > 0 && (
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="sm"
                                                    title={t(
                                                        'Copy these hours to Monday–Friday',
                                                    )}
                                                    onClick={() =>
                                                        copyToWeekdays(day.key)
                                                    }
                                                >
                                                    <Copy /> {t('Weekdays')}
                                                </Button>
                                            )}
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                        <InputError message={errors.hours} />
                    </section>

                    <div className="flex justify-end gap-2">
                        <Button asChild variant="ghost">
                            <Link href={index()}>{t('Cancel')}</Link>
                        </Button>
                        <Button type="submit" disabled={form.processing}>
                            {schedule
                                ? t('Save schedule')
                                : t('Create schedule')}
                        </Button>
                    </div>
                </form>

                {schedule ? (
                    <Holidays schedule={schedule} />
                ) : (
                    <p className="rounded-lg border border-dashed p-4 text-sm text-muted-foreground">
                        {t('Save the schedule to add holidays.')}
                    </p>
                )}
            </div>
        </>
    );
}

function Holidays({ schedule }: { schedule: BusinessSchedule }) {
    const { t, localeTag } = useTranslation();
    const form = useForm({ name: '', date: '' });

    const add = (event: React.FormEvent) => {
        event.preventDefault();
        form.post(storeHoliday.url(schedule.id), {
            preserveScroll: true,
            onSuccess: () => form.reset(),
        });
    };

    const holidays = schedule.holidays ?? [];

    return (
        <section className="space-y-4">
            <div>
                <h3 className="font-medium">{t('Holidays')}</h3>
                <p className="text-sm text-muted-foreground">
                    {t("SLA clocks don't run on these days.")}
                </p>
            </div>

            <form onSubmit={add} className="flex flex-wrap items-start gap-2">
                <div className="grid flex-1 gap-1">
                    <Input
                        aria-label={t('Holiday name')}
                        placeholder={t("e.g. New Year's Day")}
                        value={form.data.name}
                        onChange={(event) =>
                            form.setData('name', event.target.value)
                        }
                    />
                    <InputError message={form.errors.name} />
                </div>
                <div className="grid gap-1">
                    <Input
                        type="date"
                        aria-label={t('Holiday date')}
                        value={form.data.date}
                        onChange={(event) =>
                            form.setData('date', event.target.value)
                        }
                    />
                    <InputError message={form.errors.date} />
                </div>
                <Button
                    type="submit"
                    variant="outline"
                    disabled={form.processing}
                >
                    <Plus /> {t('Add holiday')}
                </Button>
            </form>

            {holidays.length === 0 ? (
                <p className="flex items-center gap-2 text-sm text-muted-foreground">
                    <CalendarOff className="size-4" /> {t('No holidays yet.')}
                </p>
            ) : (
                <ul className="divide-y rounded-xl border">
                    {holidays.map((holiday) => (
                        <li
                            key={holiday.id}
                            className="flex items-center gap-3 px-3 py-2 text-sm"
                        >
                            <span className="w-32 shrink-0 text-muted-foreground tabular-nums">
                                {new Date(
                                    `${holiday.date}T12:00:00`,
                                ).toLocaleDateString(localeTag, {
                                    dateStyle: 'medium',
                                })}
                            </span>
                            <span className="flex-1">{holiday.name}</span>
                            <Button
                                variant="ghost"
                                size="icon"
                                aria-label={t('Remove :name', {
                                    name: holiday.name,
                                })}
                                onClick={() =>
                                    router.delete(
                                        destroyHoliday.url({
                                            schedule: schedule.id,
                                            holiday: holiday.id,
                                        }),
                                        { preserveScroll: true },
                                    )
                                }
                            >
                                <Trash2 />
                            </Button>
                        </li>
                    ))}
                </ul>
            )}
        </section>
    );
}
