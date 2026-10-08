import { Check } from 'lucide-react';
import { cn } from '@/lib/utils';

/**
 * Toggleable chips for picking several options (agents, groups, events…).
 */
export function MultiSelectChips<T extends string | number>({
    options,
    value,
    onChange,
    name,
}: {
    options: { value: T; label: string }[];
    value: T[];
    onChange: (value: T[]) => void;
    /** When set, renders hidden inputs so the value posts with a native <form>. */
    name?: string;
}) {
    const toggle = (option: T) =>
        onChange(
            value.includes(option)
                ? value.filter((item) => item !== option)
                : [...value, option],
        );

    return (
        <div className="flex flex-wrap gap-1.5">
            {options.map((option) => {
                const selected = value.includes(option.value);

                return (
                    <button
                        key={String(option.value)}
                        type="button"
                        aria-pressed={selected}
                        onClick={() => toggle(option.value)}
                        className={cn(
                            'inline-flex items-center gap-1 rounded-full border px-2.5 py-1 text-xs transition-colors',
                            selected
                                ? 'border-primary/50 bg-primary/10 text-foreground'
                                : 'text-muted-foreground hover:bg-accent',
                        )}
                    >
                        {selected && <Check className="size-3 text-primary" />}
                        {option.label}
                    </button>
                );
            })}
            {name &&
                value.map((item) => (
                    <input
                        key={String(item)}
                        type="hidden"
                        name={`${name}[]`}
                        value={String(item)}
                    />
                ))}
        </div>
    );
}
