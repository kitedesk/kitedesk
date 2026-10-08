import { LocateFixed } from 'lucide-react';
import { useMemo, useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectLabel,
    SelectSeparator,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useTranslation } from '@/hooks/use-translation';

/** Radix Select can't hold an empty value; this one stands for "no choice". */
const DEFAULT = '__default';

/**
 * Timezone picker for forms: the value goes in a hidden input named `name` (empty when the
 * default is kept), grouped by region, with a button that picks this device's timezone.
 */
export function TimezoneSelect({
    id,
    name = 'timezone',
    timezones,
    defaultValue,
    emptyLabel,
}: {
    id?: string;
    name?: string;
    timezones: string[];
    defaultValue: string | null;
    emptyLabel: string;
}) {
    const { t } = useTranslation();
    const [value, setValue] = useState(defaultValue ?? '');
    const detected = useMemo(
        () => Intl.DateTimeFormat().resolvedOptions().timeZone,
        [],
    );
    const regions = useMemo(() => {
        const grouped = new Map<string, string[]>();

        for (const timezone of timezones) {
            const region = timezone.includes('/')
                ? timezone.split('/')[0]
                : t('Other');
            grouped.set(region, [...(grouped.get(region) ?? []), timezone]);
        }

        return [...grouped.entries()];
    }, [timezones, t]);

    return (
        <div className="flex gap-2">
            <input type="hidden" name={name} value={value} />
            <Select
                value={value === '' ? DEFAULT : value}
                onValueChange={(next) => setValue(next === DEFAULT ? '' : next)}
            >
                <SelectTrigger id={id} className="w-full">
                    <SelectValue />
                </SelectTrigger>
                <SelectContent className="max-h-80">
                    <SelectItem value={DEFAULT}>{emptyLabel}</SelectItem>
                    <SelectSeparator />
                    {regions.map(([region, zones]) => (
                        <SelectGroup key={region}>
                            <SelectLabel>{region}</SelectLabel>
                            {zones.map((timezone) => (
                                <SelectItem key={timezone} value={timezone}>
                                    {timezone.replaceAll('_', ' ')}
                                </SelectItem>
                            ))}
                        </SelectGroup>
                    ))}
                </SelectContent>
            </Select>
            {timezones.includes(detected) && value !== detected && (
                <Button
                    type="button"
                    variant="outline"
                    size="icon"
                    title={t('Use this device’s timezone (:timezone)', {
                        timezone: detected,
                    })}
                    onClick={() => setValue(detected)}
                >
                    <LocateFixed />
                </Button>
            )}
        </div>
    );
}

/**
 * Language picker for forms, posting the locale code (empty for the installation default).
 */
export function LocaleSelect({
    id,
    name = 'locale',
    locales,
    defaultValue,
    emptyLabel,
}: {
    id?: string;
    name?: string;
    locales: Record<string, string>;
    defaultValue: string | null;
    emptyLabel: string;
}) {
    const [value, setValue] = useState(defaultValue ?? '');

    return (
        <>
            <input type="hidden" name={name} value={value} />
            <Select
                value={value === '' ? DEFAULT : value}
                onValueChange={(next) => setValue(next === DEFAULT ? '' : next)}
            >
                <SelectTrigger id={id} className="w-full">
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value={DEFAULT}>{emptyLabel}</SelectItem>
                    <SelectSeparator />
                    {Object.entries(locales).map(([code, label]) => (
                        <SelectItem key={code} value={code}>
                            {label}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
        </>
    );
}
