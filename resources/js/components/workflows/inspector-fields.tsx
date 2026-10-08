import { Braces } from 'lucide-react';
import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { PLACEHOLDERS } from '@/components/workflows/workflow-fields';
import { useTranslation } from '@/hooks/use-translation';
import type { Option } from '@/types';

const NONE = '__none';

export function Field({
    label,
    hint,
    children,
}: {
    label: string;
    hint?: string;
    children: ReactNode;
}) {
    return (
        <div className="grid gap-1.5">
            <Label className="text-xs">{label}</Label>
            {children}
            {hint && <p className="text-xs text-muted-foreground">{hint}</p>}
        </div>
    );
}

/**
 * A select whose empty choice ("Don't change", "Any"…) stores an empty string.
 */
export function OptionSelect({
    value,
    options,
    onChange,
    emptyLabel,
    label,
}: {
    value: string;
    options: Option<string | number>[];
    onChange: (value: string) => void;
    emptyLabel?: string;
    label: string;
}) {
    return (
        <Select
            value={value === '' ? (emptyLabel ? NONE : undefined) : value}
            onValueChange={(next) => onChange(next === NONE ? '' : next)}
        >
            <SelectTrigger className="w-full" aria-label={label}>
                <SelectValue placeholder={emptyLabel ?? label} />
            </SelectTrigger>
            <SelectContent>
                {emptyLabel && (
                    <SelectItem value={NONE}>{emptyLabel}</SelectItem>
                )}
                {options.map((option) => (
                    <SelectItem key={option.value} value={String(option.value)}>
                        {option.label}
                    </SelectItem>
                ))}
            </SelectContent>
        </Select>
    );
}

/**
 * Comma-separated values edited as text (tags, email addresses).
 */
export function ListInput({
    value,
    onChange,
    placeholder,
    label,
}: {
    value: string[];
    onChange: (value: string[]) => void;
    placeholder?: string;
    label: string;
}) {
    return (
        <Input
            aria-label={label}
            defaultValue={value.join(', ')}
            placeholder={placeholder}
            onChange={(event) =>
                onChange(
                    event.target.value
                        .split(',')
                        .map((item) => item.trim())
                        .filter(Boolean),
                )
            }
        />
    );
}

/**
 * A menu of `{{placeholders}}` to insert into a text.
 */
export function PlaceholderMenu({
    onInsert,
    variables = [],
}: {
    onInsert: (token: string) => void;
    variables?: string[];
}) {
    const { t } = useTranslation();
    const groups = [
        ...PLACEHOLDERS,
        ...(variables.length
            ? [
                  {
                      group: 'Variables',
                      keys: variables.map((name) => `vars.${name}`),
                  },
              ]
            : []),
    ];

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    className="h-7 gap-1 text-xs"
                >
                    <Braces className="size-3.5" />
                    {t('Insert placeholder')}
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent
                align="end"
                className="max-h-80 overflow-y-auto"
            >
                {groups.map((group) => (
                    <DropdownMenuGroup key={group.group}>
                        <DropdownMenuLabel className="text-xs text-muted-foreground">
                            {t(group.group)}
                        </DropdownMenuLabel>
                        {group.keys.map((key) => (
                            <DropdownMenuItem
                                key={key}
                                className="font-mono text-xs"
                                onSelect={() => onInsert(`{{${key}}}`)}
                            >
                                {key}
                            </DropdownMenuItem>
                        ))}
                    </DropdownMenuGroup>
                ))}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

/**
 * A one-line text input with the placeholder menu.
 */
export function TemplateInput({
    label,
    value,
    onChange,
    variables,
    placeholder,
    hint,
}: {
    label: string;
    value: string;
    onChange: (value: string) => void;
    variables: string[];
    placeholder?: string;
    hint?: string;
}) {
    return (
        <div className="grid gap-1.5">
            <div className="flex items-center justify-between gap-2">
                <Label className="text-xs">{label}</Label>
                <PlaceholderMenu
                    variables={variables}
                    onInsert={(token) => onChange(`${value}${token}`)}
                />
            </div>
            <Input
                value={value}
                placeholder={placeholder}
                onChange={(event) => onChange(event.target.value)}
            />
            {hint && <p className="text-xs text-muted-foreground">{hint}</p>}
        </div>
    );
}

/**
 * A multi-line text input with the placeholder menu.
 */
export function TemplateTextarea({
    label,
    value,
    onChange,
    variables,
    rows = 4,
    mono = false,
}: {
    label: string;
    value: string;
    onChange: (value: string) => void;
    variables: string[];
    rows?: number;
    mono?: boolean;
}) {
    return (
        <div className="grid gap-1.5">
            <div className="flex items-center justify-between gap-2">
                <Label className="text-xs">{label}</Label>
                <PlaceholderMenu
                    variables={variables}
                    onInsert={(token) => onChange(`${value}${token}`)}
                />
            </div>
            <textarea
                value={value}
                rows={rows}
                onChange={(event) => onChange(event.target.value)}
                className={`w-full rounded-md border bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30 ${mono ? 'font-mono text-xs' : ''}`}
            />
        </div>
    );
}
