import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

/**
 * Labeled text input (or textarea) with its validation error, for admin dialogs and forms.
 */
export function TextField({
    id,
    label,
    value,
    onChange,
    error,
    multiline = false,
    required = false,
    placeholder,
    disabled = false,
}: {
    id: string;
    label?: string;
    value: string;
    onChange: (value: string) => void;
    error?: string;
    multiline?: boolean;
    required?: boolean;
    placeholder?: string;
    disabled?: boolean;
}) {
    return (
        <div className="grid gap-2">
            {label && <Label htmlFor={id}>{label}</Label>}
            {multiline ? (
                <textarea
                    id={id}
                    value={value}
                    rows={2}
                    placeholder={placeholder}
                    required={required}
                    disabled={disabled}
                    onChange={(event) => onChange(event.target.value)}
                    className="w-full rounded-md border bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-50 dark:bg-input/30"
                />
            ) : (
                <Input
                    id={id}
                    value={value}
                    placeholder={placeholder}
                    required={required}
                    disabled={disabled}
                    onChange={(event) => onChange(event.target.value)}
                />
            )}
            <InputError message={error} />
        </div>
    );
}
