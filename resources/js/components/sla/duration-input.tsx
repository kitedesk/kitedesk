import { useEffect, useState } from 'react';
import { useTranslation } from '@/hooks/use-translation';
import { cn } from '@/lib/utils';

/**
 * Minutes stored, hours + minutes edited. Empty means "not tracked".
 */
export function DurationInput({
    value,
    onChange,
    invalid,
    label,
}: {
    value: number | null | undefined;
    onChange: (minutes: number | null) => void;
    invalid?: boolean;
    label: string;
}) {
    const { t } = useTranslation();
    const [hours, setHours] = useState(
        value ? String(Math.floor(value / 60)) : '',
    );
    const [minutes, setMinutes] = useState(value ? String(value % 60) : '');

    // Sync from props only when the value changed outside this input (e.g. form reset).
    useEffect(() => {
        const local =
            hours === '' && minutes === ''
                ? null
                : (Number(hours) || 0) * 60 + (Number(minutes) || 0);

        if ((value ?? null) !== local) {
            setHours(value ? String(Math.floor(value / 60)) : '');
            setMinutes(value ? String(value % 60) : '');
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [value]);

    const emit = (nextHours: string, nextMinutes: string) => {
        if (nextHours === '' && nextMinutes === '') {
            onChange(null);

            return;
        }

        const total =
            (Number(nextHours) || 0) * 60 + (Number(nextMinutes) || 0);
        onChange(total);
    };

    const fieldClass =
        'h-8 w-full min-w-0 bg-transparent text-right text-sm tabular-nums outline-none placeholder:text-muted-foreground/60';

    return (
        <div
            className={cn(
                'flex items-center gap-1 rounded-md border px-2 shadow-xs focus-within:ring-[3px] focus-within:ring-ring/50 dark:bg-input/30',
                invalid && 'border-destructive',
            )}
        >
            <input
                type="number"
                min={0}
                inputMode="numeric"
                aria-label={t(':label hours', { label })}
                placeholder="—"
                value={hours}
                onChange={(event) => {
                    setHours(event.target.value);
                    emit(event.target.value, minutes);
                }}
                className={fieldClass}
            />
            <span className="text-xs text-muted-foreground">{t('h')}</span>
            <input
                type="number"
                min={0}
                max={59}
                inputMode="numeric"
                aria-label={t(':label minutes', { label })}
                placeholder="—"
                value={minutes}
                onChange={(event) => {
                    setMinutes(event.target.value);
                    emit(hours, event.target.value);
                }}
                className={fieldClass}
            />
            <span className="text-xs text-muted-foreground">{t('m')}</span>
        </div>
    );
}
