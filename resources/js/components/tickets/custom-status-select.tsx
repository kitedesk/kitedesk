import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectLabel,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useTranslation } from '@/hooks/use-translation';
import { statusColors, statusLabel } from '@/lib/tickets';
import { cn } from '@/lib/utils';
import type { CustomStatusOption, TicketStatus } from '@/types';

/**
 * Active custom statuses grouped under their category. The current value stays listed even
 * when its status was turned off.
 */
export function CustomStatusSelect({
    value,
    statuses,
    onChange,
    exclude = [],
    disabled = false,
    placeholder,
    className,
}: {
    value: string | undefined;
    statuses: CustomStatusOption[];
    onChange: (value: string) => void;
    /** Categories that can't be picked here. */
    exclude?: TicketStatus[];
    disabled?: boolean;
    placeholder?: string;
    className?: string;
}) {
    useTranslation();
    const choices = statuses.filter(
        (status) =>
            String(status.id) === value ||
            (status.is_active && !exclude.includes(status.category)),
    );
    const categories = [...new Set(choices.map((status) => status.category))];

    return (
        <Select value={value} disabled={disabled} onValueChange={onChange}>
            <SelectTrigger className={cn('h-9 w-full', className)}>
                <SelectValue placeholder={placeholder} />
            </SelectTrigger>
            <SelectContent>
                {categories.map((category) => (
                    <SelectGroup key={category}>
                        <SelectLabel className="text-[11px] tracking-wide uppercase">
                            {statusLabel(category)}
                        </SelectLabel>
                        {choices
                            .filter((status) => status.category === category)
                            .map((status) => (
                                <SelectItem
                                    key={status.id}
                                    value={String(status.id)}
                                >
                                    <span
                                        className={cn(
                                            'size-2 rounded-full',
                                            statusColors[status.color].dot,
                                        )}
                                    />
                                    {status.name}
                                </SelectItem>
                            ))}
                    </SelectGroup>
                ))}
            </SelectContent>
        </Select>
    );
}
