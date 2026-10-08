import { cn } from '@/lib/utils';

export function ActiveSwitch({
    checked,
    onChange,
    label,
    disabled,
}: {
    checked: boolean;
    onChange: (checked: boolean) => void;
    label: string;
    disabled?: boolean;
}) {
    return (
        <button
            type="button"
            role="switch"
            aria-checked={checked}
            aria-label={label}
            disabled={disabled}
            onClick={() => onChange(!checked)}
            className={cn(
                'relative inline-flex h-5 w-9 shrink-0 items-center rounded-full border border-transparent transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none disabled:opacity-50',
                checked ? 'bg-primary' : 'bg-input',
            )}
        >
            <span
                className={cn(
                    'pointer-events-none block size-4 rounded-full bg-background shadow-sm transition-transform',
                    checked ? 'translate-x-4' : 'translate-x-0',
                )}
            />
        </button>
    );
}
