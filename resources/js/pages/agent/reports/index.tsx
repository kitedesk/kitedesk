import { Head, router } from '@inertiajs/react';
import { Download } from 'lucide-react';
import { useState } from 'react';
import { DailyChart } from '@/components/reports/daily-chart';
import type { DailyPoint } from '@/components/reports/daily-chart';
import { AnimatedNumber } from '@/components/ui/animated-number';
import { AnimatedTabs } from '@/components/ui/animated-tabs';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { usePermissions } from '@/hooks/use-permissions';
import { useTranslation } from '@/hooks/use-translation';
import { t as translate } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { exportMethod, index } from '@/routes/agent/reports';
import type { CategoryNode, NamedRecord } from '@/types';

type Row = {
    key: string;
    label: string;
    created: number;
    solved: number;
    median_first_response_minutes: number | null;
    median_resolution_minutes: number | null;
    satisfaction: number | null;
    satisfaction_responses: number;
};

type Dimension = 'agent' | 'group' | 'category' | 'channel';

type Report = {
    range: { from: string; to: string };
    totals: {
        created: number;
        solved: number;
        backlog: number;
        median_first_response_minutes: number | null;
        median_resolution_minutes: number | null;
        sla_compliance: number | null;
        sla_measured: number;
        satisfaction: number | null;
        satisfaction_responses: number;
    };
    daily: DailyPoint[];
    breakdowns: Record<Dimension, Row[]>;
};

type Filters = {
    range: string;
    from?: string;
    to?: string;
    group_id?: string;
    category_id?: string;
    assignee_id?: string;
};

type Props = {
    report: Report;
    filters: Filters;
    satisfactionEnabled: boolean;
    options: {
        groups: NamedRecord[];
        categories: CategoryNode[];
        agents: NamedRecord[];
    };
};

const ANY = '__any';

/**
 * "45 min", "3 h 20 min", "2 d 4 h".
 */
function formatDuration(minutes: number | null): string {
    if (minutes === null) {
        return '—';
    }

    if (minutes < 60) {
        return translate(':count min', { count: minutes });
    }

    if (minutes < 60 * 24) {
        const hours = Math.floor(minutes / 60);
        const rest = minutes % 60;

        return rest
            ? translate(':hours h :minutes min', { hours, minutes: rest })
            : translate(':count h', { count: hours });
    }

    const days = Math.floor(minutes / (60 * 24));
    const hours = Math.floor((minutes % (60 * 24)) / 60);

    return hours
        ? translate(':days d :hours h', { days, hours })
        : translate(':count d', { count: days });
}

export default function Reports({
    report,
    filters,
    options,
    satisfactionEnabled,
}: Props) {
    const { t, localeTag } = useTranslation();
    const { can } = usePermissions();
    const [dimension, setDimension] = useState<Dimension>('agent');
    const [customRange, setCustomRange] = useState({
        from: filters.from ?? report.range.from,
        to: filters.to ?? report.range.to,
    });

    const visit = (changes: Partial<Filters>) => {
        const next = { ...filters, ...changes };
        const query = Object.fromEntries(
            Object.entries(next).filter(
                ([key, value]) =>
                    value !== undefined &&
                    value !== '' &&
                    value !== ANY &&
                    (next.range === 'custom' ||
                        (key !== 'from' && key !== 'to')),
            ),
        );

        router.get(index.url(), query, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    const exportQuery = Object.fromEntries(
        Object.entries(filters).filter(([, value]) => value),
    );
    const date = (value: string) =>
        new Date(`${value}T00:00:00`).toLocaleDateString(localeTag, {
            day: 'numeric',
            month: 'short',
            year: 'numeric',
        });

    const showSatisfaction =
        satisfactionEnabled || report.totals.satisfaction_responses > 0;
    const satisfactionValue = (score: number | null) =>
        score === null ? '—' : `${score.toLocaleString(localeTag)}%`;

    const tiles = [
        {
            label: t('Created'),
            value: <AnimatedNumber value={report.totals.created} useGrouping />,
        },
        {
            label: t('Solved'),
            value: <AnimatedNumber value={report.totals.solved} useGrouping />,
        },
        {
            label: t('Open backlog'),
            hint: t('Unsolved right now'),
            value: <AnimatedNumber value={report.totals.backlog} useGrouping />,
        },
        {
            label: t('Median first reply'),
            value: formatDuration(report.totals.median_first_response_minutes),
        },
        {
            label: t('Median resolution'),
            value: formatDuration(report.totals.median_resolution_minutes),
        },
        {
            label: t('SLA kept'),
            hint:
                report.totals.sla_measured > 0
                    ? t('of :count solved tickets with an SLA', {
                          count: report.totals.sla_measured,
                      })
                    : t('No solved tickets with an SLA'),
            value:
                report.totals.sla_compliance === null ? (
                    '—'
                ) : (
                    <AnimatedNumber
                        value={report.totals.sla_compliance}
                        decimals={1}
                        suffix="%"
                    />
                ),
        },
        ...(showSatisfaction
            ? [
                  {
                      label: t('Satisfaction (CSAT)'),
                      hint:
                          report.totals.satisfaction_responses > 0
                              ? t('4–5 stars, of :count ratings', {
                                    count: report.totals.satisfaction_responses,
                                })
                              : t('No ratings yet'),
                      value:
                          report.totals.satisfaction === null ? (
                              '—'
                          ) : (
                              <AnimatedNumber
                                  value={report.totals.satisfaction}
                                  decimals={1}
                                  suffix="%"
                              />
                          ),
                  },
              ]
            : []),
    ];

    const dimensions: { id: Dimension; label: string }[] = [
        { id: 'agent', label: t('Agents') },
        { id: 'group', label: t('Groups') },
        { id: 'category', label: t('Categories') },
        { id: 'channel', label: t('Channels') },
    ];

    return (
        <>
            <Head title={t('Reports')} />

            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <h1 className="text-xl font-semibold tracking-tight">
                            {t('Reports')}
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {t(':from to :to', {
                                from: date(report.range.from),
                                to: date(report.range.to),
                            })}
                        </p>
                    </div>
                    {can('reports.export') && (
                        <Button variant="outline" size="sm" asChild>
                            <a href={exportMethod.url({ query: exportQuery })}>
                                <Download /> {t('Export CSV')}
                            </a>
                        </Button>
                    )}
                </div>

                <div className="flex flex-wrap items-center gap-2">
                    <Select
                        value={filters.range}
                        onValueChange={(range) => visit({ range })}
                    >
                        <SelectTrigger
                            className="h-8 w-auto"
                            aria-label={t('Period')}
                        >
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="7">
                                {t('Last 7 days')}
                            </SelectItem>
                            <SelectItem value="30">
                                {t('Last 30 days')}
                            </SelectItem>
                            <SelectItem value="90">
                                {t('Last 90 days')}
                            </SelectItem>
                            <SelectItem value="custom">
                                {t('Custom dates')}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    {filters.range === 'custom' && (
                        <form
                            className="flex items-center gap-1.5"
                            onSubmit={(event) => {
                                event.preventDefault();
                                visit(customRange);
                            }}
                        >
                            <Input
                                type="date"
                                aria-label={t('From')}
                                value={customRange.from}
                                onChange={(event) =>
                                    setCustomRange({
                                        ...customRange,
                                        from: event.target.value,
                                    })
                                }
                                className="h-8 w-auto"
                            />
                            <span className="text-sm text-muted-foreground">
                                –
                            </span>
                            <Input
                                type="date"
                                aria-label={t('To')}
                                value={customRange.to}
                                onChange={(event) =>
                                    setCustomRange({
                                        ...customRange,
                                        to: event.target.value,
                                    })
                                }
                                className="h-8 w-auto"
                            />
                            <Button type="submit" size="sm" variant="secondary">
                                {t('Apply')}
                            </Button>
                        </form>
                    )}
                    <FilterSelect
                        label={t('All groups')}
                        value={filters.group_id}
                        options={options.groups.map((group) => ({
                            value: String(group.id),
                            label: group.name,
                        }))}
                        onChange={(group_id) => visit({ group_id })}
                    />
                    <FilterSelect
                        label={t('All categories')}
                        value={filters.category_id}
                        options={options.categories.map((category) => ({
                            value: String(category.id),
                            label: category.name,
                        }))}
                        onChange={(category_id) => visit({ category_id })}
                    />
                    <FilterSelect
                        label={t('All agents')}
                        value={filters.assignee_id}
                        options={options.agents.map((agent) => ({
                            value: String(agent.id),
                            label: agent.name,
                        }))}
                        onChange={(assignee_id) => visit({ assignee_id })}
                    />
                </div>

                <dl
                    className={cn(
                        'grid grid-cols-2 gap-3 md:grid-cols-3',
                        showSatisfaction
                            ? 'lg:grid-cols-4 2xl:grid-cols-7'
                            : 'xl:grid-cols-6',
                    )}
                >
                    {tiles.map((tile) => (
                        <div
                            key={tile.label}
                            className="rounded-xl border bg-card p-4 shadow-xs"
                        >
                            <dt className="text-xs font-medium text-muted-foreground">
                                {tile.label}
                            </dt>
                            <dd className="mt-1 text-2xl font-semibold tracking-tight tabular-nums">
                                {tile.value}
                            </dd>
                            {tile.hint && (
                                <dd className="mt-0.5 text-xs text-muted-foreground">
                                    {tile.hint}
                                </dd>
                            )}
                        </div>
                    ))}
                </dl>

                <section className="rounded-xl border bg-card p-4 shadow-xs">
                    <h2 className="mb-3 text-sm font-semibold">
                        {t('Created vs solved per day')}
                    </h2>
                    <DailyChart data={report.daily} />
                </section>

                <section className="rounded-xl border bg-card shadow-xs">
                    <div className="border-b p-3">
                        <AnimatedTabs
                            tabs={dimensions}
                            activeTab={dimension}
                            onChange={(id) => setDimension(id as Dimension)}
                            renderContent={false}
                            layoutId="report-dimension"
                            variant="pill"
                        />
                    </div>
                    {report.breakdowns[dimension].length === 0 ? (
                        <p className="p-6 text-center text-sm text-muted-foreground">
                            {t('No tickets in this period.')}
                        </p>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full min-w-[36rem] text-sm">
                                <thead>
                                    <tr className="border-b text-left text-xs text-muted-foreground">
                                        <th className="px-4 py-2 font-medium">
                                            {
                                                dimensions.find(
                                                    (item) =>
                                                        item.id === dimension,
                                                )?.label
                                            }
                                        </th>
                                        <th className="px-4 py-2 text-right font-medium">
                                            {t('Created')}
                                        </th>
                                        <th className="px-4 py-2 text-right font-medium">
                                            {t('Solved')}
                                        </th>
                                        <th className="px-4 py-2 text-right font-medium">
                                            {t('Median first reply')}
                                        </th>
                                        <th className="px-4 py-2 text-right font-medium">
                                            {t('Median resolution')}
                                        </th>
                                        {showSatisfaction && (
                                            <th className="px-4 py-2 text-right font-medium">
                                                {t('CSAT')}
                                            </th>
                                        )}
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {report.breakdowns[dimension].map((row) => (
                                        <tr key={row.key}>
                                            <td className="px-4 py-2">
                                                {row.label}
                                            </td>
                                            <td className="px-4 py-2 text-right tabular-nums">
                                                {row.created}
                                            </td>
                                            <td className="px-4 py-2 text-right tabular-nums">
                                                {row.solved}
                                            </td>
                                            <td className="px-4 py-2 text-right tabular-nums">
                                                {formatDuration(
                                                    row.median_first_response_minutes,
                                                )}
                                            </td>
                                            <td className="px-4 py-2 text-right tabular-nums">
                                                {formatDuration(
                                                    row.median_resolution_minutes,
                                                )}
                                            </td>
                                            {showSatisfaction && (
                                                <td
                                                    className="px-4 py-2 text-right tabular-nums"
                                                    title={t(':count ratings', {
                                                        count: row.satisfaction_responses,
                                                    })}
                                                >
                                                    {satisfactionValue(
                                                        row.satisfaction,
                                                    )}
                                                </td>
                                            )}
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </section>
            </div>
        </>
    );
}

Reports.layout = {
    breadcrumbs: [{ title: 'Reports', href: index() }],
};

function FilterSelect({
    label,
    value,
    options,
    onChange,
}: {
    label: string;
    value?: string;
    options: { value: string; label: string }[];
    onChange: (value: string | undefined) => void;
}) {
    return (
        <Select
            value={value ?? ANY}
            onValueChange={(next) => onChange(next === ANY ? undefined : next)}
        >
            <SelectTrigger className="h-8 w-auto min-w-36" aria-label={label}>
                <SelectValue />
            </SelectTrigger>
            <SelectContent>
                <SelectItem value={ANY}>{label}</SelectItem>
                {options.map((option) => (
                    <SelectItem key={option.value} value={option.value}>
                        {option.label}
                    </SelectItem>
                ))}
            </SelectContent>
        </Select>
    );
}
