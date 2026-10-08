import { useEffect, useRef, useState } from 'react';
import { useTranslation } from '@/hooks/use-translation';
import { cn } from '@/lib/utils';

export type DailyPoint = { date: string; created: number; solved: number };

const HEIGHT = 220;
const PADDING = { top: 12, right: 8, bottom: 24, left: 32 };

/**
 * Created vs solved per day as grouped bars. Series colors come from the validated
 * categorical palette (slot 1 blue, slot 2 orange) with separate dark-mode steps.
 * Every day has a hover/focus target wider than its bars, and the same numbers are
 * available as a table. `labels` renames the two series for other daily counts.
 */
export function DailyChart({
    data,
    labels,
    description,
}: {
    data: DailyPoint[];
    labels?: { created: string; solved: string };
    description?: string;
}) {
    const { t, localeTag } = useTranslation();
    const container = useRef<HTMLDivElement>(null);
    const [width, setWidth] = useState(640);
    const [active, setActive] = useState<number | null>(null);
    const [showTable, setShowTable] = useState(false);

    useEffect(() => {
        const element = container.current;

        if (!element) {
            return;
        }

        const observer = new ResizeObserver(([entry]) =>
            setWidth(Math.max(280, Math.floor(entry.contentRect.width))),
        );
        observer.observe(element);

        return () => observer.disconnect();
    }, []);

    const series = [
        {
            key: 'created' as const,
            label: labels?.created ?? t('Created'),
            className: 'fill-[var(--series-1)]',
        },
        {
            key: 'solved' as const,
            label: labels?.solved ?? t('Solved'),
            className: 'fill-[var(--series-2)]',
        },
    ];
    const max = Math.max(
        1,
        ...data.flatMap((day) => [day.created, day.solved]),
    );
    const ticks = niceTicks(max);
    const top = ticks[ticks.length - 1];
    const plotWidth = width - PADDING.left - PADDING.right;
    const plotHeight = HEIGHT - PADDING.top - PADDING.bottom;
    const slot = plotWidth / Math.max(1, data.length);
    const barWidth = Math.max(1, Math.min(14, (slot - 4) / 2 - 1));
    const y = (value: number) =>
        PADDING.top + plotHeight - (value / top) * plotHeight;
    const dayLabel = (date: string, style: 'short' | 'long') =>
        new Date(`${date}T00:00:00`).toLocaleDateString(
            localeTag,
            style === 'short'
                ? { day: 'numeric', month: 'short' }
                : { weekday: 'short', day: 'numeric', month: 'long' },
        );
    const labelEvery = Math.ceil(
        data.length / Math.max(1, Math.floor(plotWidth / 64)),
    );
    const hovered = active !== null ? data[active] : null;

    return (
        <div className="[--series-1:#2a78d6] [--series-2:#eb6834] dark:[--series-1:#3987e5] dark:[--series-2:#d95926]">
            <div className="mb-3 flex flex-wrap items-center justify-between gap-2">
                <ul className="flex items-center gap-4 text-xs text-muted-foreground">
                    {series.map((item) => (
                        <li
                            key={item.key}
                            className="flex items-center gap-1.5"
                        >
                            <svg className="size-2.5" aria-hidden>
                                <rect
                                    width="10"
                                    height="10"
                                    rx="2"
                                    className={item.className}
                                />
                            </svg>
                            {item.label}
                        </li>
                    ))}
                </ul>
                <button
                    type="button"
                    onClick={() => setShowTable((value) => !value)}
                    className="text-xs font-medium text-primary hover:underline"
                >
                    {showTable ? t('Show chart') : t('Show table')}
                </button>
            </div>

            {showTable ? (
                <div className="max-h-72 overflow-y-auto rounded-lg border">
                    <table className="w-full text-sm">
                        <thead className="sticky top-0 bg-muted text-left text-xs text-muted-foreground">
                            <tr>
                                <th className="px-3 py-2 font-medium">
                                    {t('Date')}
                                </th>
                                <th className="px-3 py-2 text-right font-medium">
                                    {series[0].label}
                                </th>
                                <th className="px-3 py-2 text-right font-medium">
                                    {series[1].label}
                                </th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {data.map((day) => (
                                <tr key={day.date}>
                                    <td className="px-3 py-1.5">
                                        {dayLabel(day.date, 'long')}
                                    </td>
                                    <td className="px-3 py-1.5 text-right tabular-nums">
                                        {day.created}
                                    </td>
                                    <td className="px-3 py-1.5 text-right tabular-nums">
                                        {day.solved}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            ) : (
                <div ref={container} className="relative">
                    <svg
                        width={width}
                        height={HEIGHT}
                        role="img"
                        aria-label={
                            description ??
                            t('Tickets created and solved per day')
                        }
                        className="block max-w-full"
                        onPointerLeave={() => setActive(null)}
                    >
                        {ticks.map((tick) => (
                            <g key={tick}>
                                <line
                                    x1={PADDING.left}
                                    x2={width - PADDING.right}
                                    y1={y(tick)}
                                    y2={y(tick)}
                                    className="stroke-border"
                                    strokeDasharray={
                                        tick === 0 ? undefined : '2 4'
                                    }
                                />
                                <text
                                    x={PADDING.left - 6}
                                    y={y(tick)}
                                    dy="0.32em"
                                    textAnchor="end"
                                    className="fill-muted-foreground text-[10px] tabular-nums"
                                >
                                    {tick}
                                </text>
                            </g>
                        ))}

                        {data.map((day, index) => {
                            const center =
                                PADDING.left + slot * index + slot / 2;

                            return (
                                <g
                                    key={day.date}
                                    tabIndex={0}
                                    role="listitem"
                                    aria-label={`${dayLabel(day.date, 'long')}: ${series[0].label} ${day.created}, ${series[1].label} ${day.solved}`}
                                    onPointerEnter={() => setActive(index)}
                                    onFocus={() => setActive(index)}
                                    onBlur={() => setActive(null)}
                                    className="outline-none"
                                >
                                    <rect
                                        x={center - slot / 2}
                                        y={PADDING.top}
                                        width={slot}
                                        height={plotHeight}
                                        className={cn(
                                            'fill-transparent',
                                            active === index && 'fill-muted/60',
                                        )}
                                    />
                                    {series.map((item, position) => {
                                        const value = day[item.key];
                                        const x =
                                            position === 0
                                                ? center - barWidth - 1
                                                : center + 1;

                                        return value > 0 ? (
                                            <path
                                                key={item.key}
                                                d={topRoundedBar(
                                                    x,
                                                    y(value),
                                                    barWidth,
                                                    y(0) - y(value),
                                                )}
                                                className={item.className}
                                            />
                                        ) : null;
                                    })}
                                    {index % labelEvery === 0 && (
                                        <text
                                            x={center}
                                            y={HEIGHT - 6}
                                            textAnchor="middle"
                                            className="fill-muted-foreground text-[10px]"
                                        >
                                            {dayLabel(day.date, 'short')}
                                        </text>
                                    )}
                                </g>
                            );
                        })}
                    </svg>

                    {hovered && active !== null && (
                        <div
                            className="pointer-events-none absolute top-0 z-10 rounded-md border bg-popover px-2.5 py-1.5 text-xs shadow-md"
                            style={{
                                left: Math.min(
                                    Math.max(
                                        0,
                                        PADDING.left +
                                            slot * active +
                                            slot / 2 -
                                            70,
                                    ),
                                    width - 150,
                                ),
                            }}
                        >
                            <p className="mb-1 font-medium">
                                {dayLabel(hovered.date, 'long')}
                            </p>
                            {series.map((item) => (
                                <p
                                    key={item.key}
                                    className="flex items-center gap-1.5 text-muted-foreground"
                                >
                                    <svg className="size-2" aria-hidden>
                                        <rect
                                            width="8"
                                            height="8"
                                            rx="2"
                                            className={item.className}
                                        />
                                    </svg>
                                    <strong className="font-semibold text-foreground tabular-nums">
                                        {hovered[item.key]}
                                    </strong>
                                    {item.label}
                                </p>
                            ))}
                        </div>
                    )}
                </div>
            )}
        </div>
    );
}

/**
 * Rounded "nice" axis ticks from 0 up to at least the maximum.
 */
function niceTicks(max: number): number[] {
    const rough = max / 4;
    const magnitude = 10 ** Math.floor(Math.log10(rough));
    // Ticket counts are whole numbers, so steps never go below 1.
    const step = Math.max(
        1,
        [1, 2, 5, 10]
            .map((factor) => factor * magnitude)
            .find((candidate) => candidate >= rough) ?? rough,
    );
    const steps = Math.max(1, Math.ceil(max / step));

    return Array.from({ length: steps + 1 }, (_, index) =>
        Math.round(index * step),
    );
}

/**
 * A bar anchored to the baseline with a 4px rounded data end (top corners only).
 */
function topRoundedBar(
    x: number,
    top: number,
    width: number,
    height: number,
): string {
    const radius = Math.min(4, width / 2, height);

    return [
        `M${x},${top + height}`,
        `V${top + radius}`,
        `Q${x},${top} ${x + radius},${top}`,
        `H${x + width - radius}`,
        `Q${x + width},${top} ${x + width},${top + radius}`,
        `V${top + height}`,
        'Z',
    ].join(' ');
}
