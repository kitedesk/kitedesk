import { useState } from 'react';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useTranslation } from '@/hooks/use-translation';
import { displayValue } from '@/lib/tickets';
import type { TicketField } from '@/types';

/**
 * Input for one custom ticket field. With `commitOnBlur`, text inputs report changes
 * on blur, so the ticket panel saves once per edit rather than on every keystroke.
 */
export function CustomFieldInput({
    field,
    value,
    onChange,
    disabled,
    id,
    commitOnBlur = false,
}: {
    field: TicketField;
    value: unknown;
    onChange?: (value: unknown) => void;
    disabled?: boolean;
    id?: string;
    commitOnBlur?: boolean;
}) {
    const { t } = useTranslation();
    const [draft, setDraft] = useState(displayValue(value));

    const edit = (text: string) => {
        setDraft(text);

        if (!commitOnBlur) {
            onChange?.(text);
        }
    };

    const commit = () => {
        if (commitOnBlur && draft !== displayValue(value)) {
            onChange?.(draft);
        }
    };

    if (field.type === 'select') {
        return (
            <Select
                value={typeof value === 'string' ? value : undefined}
                disabled={disabled}
                onValueChange={onChange}
            >
                <SelectTrigger id={id} className="h-9 w-full">
                    <SelectValue placeholder={t('Select…')} />
                </SelectTrigger>
                <SelectContent>
                    {(field.options ?? []).map((option) => (
                        <SelectItem key={option} value={option}>
                            {option}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
        );
    }

    if (field.type === 'checkbox') {
        return (
            <label className="flex items-center gap-2 text-sm">
                <input
                    id={id}
                    type="checkbox"
                    checked={value === true || value === '1' || value === 1}
                    disabled={disabled}
                    onChange={(event) => onChange?.(event.target.checked)}
                    className="size-4 accent-[var(--primary)]"
                />
                {t('Yes')}
            </label>
        );
    }

    if (field.type === 'textarea') {
        return (
            <textarea
                id={id}
                value={draft}
                disabled={disabled}
                rows={3}
                onChange={(event) => edit(event.target.value)}
                onBlur={commit}
                className="rounded-md border bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30"
            />
        );
    }

    return (
        <Input
            id={id}
            type={
                field.type === 'number'
                    ? 'number'
                    : field.type === 'date'
                      ? 'date'
                      : 'text'
            }
            value={draft}
            disabled={disabled}
            onChange={(event) => edit(event.target.value)}
            onBlur={commit}
        />
    );
}
