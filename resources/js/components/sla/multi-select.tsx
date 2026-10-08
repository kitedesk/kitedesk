import { ChevronDown } from 'lucide-react';
import {
    DropdownMenu,
    DropdownMenuCheckboxItem,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useTranslation } from '@/hooks/use-translation';
import { cn } from '@/lib/utils';

/**
 * Compact multi-select built on the dropdown menu; an empty selection means "any".
 */
export function MultiSelect<T extends string | number>({
    options,
    value,
    onChange,
    anyLabel,
    id,
}: {
    options: { value: T; label: string }[];
    value: T[];
    onChange: (value: T[]) => void;
    anyLabel?: string;
    id?: string;
}) {
    const { t } = useTranslation();
    const selected = options.filter((option) => value.includes(option.value));

    return (
        <DropdownMenu>
            <DropdownMenuTrigger
                id={id}
                className="flex min-h-9 w-full items-center justify-between gap-2 rounded-md border bg-transparent px-3 py-1.5 text-left text-sm shadow-xs outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30"
            >
                <span className="flex flex-wrap gap-1">
                    {selected.length === 0 ? (
                        <span className="text-muted-foreground">
                            {anyLabel ?? t('Any')}
                        </span>
                    ) : (
                        selected.map((option) => (
                            <span
                                key={String(option.value)}
                                className="rounded bg-muted px-1.5 py-0.5 text-xs"
                            >
                                {option.label}
                            </span>
                        ))
                    )}
                </span>
                <ChevronDown className="size-4 shrink-0 opacity-50" />
            </DropdownMenuTrigger>
            <DropdownMenuContent className="max-h-72 w-(--radix-dropdown-menu-trigger-width) overflow-y-auto">
                {options.length === 0 && (
                    <p className="px-2 py-1.5 text-sm text-muted-foreground">
                        {t('Nothing to choose from yet.')}
                    </p>
                )}
                {options.map((option) => {
                    const isChecked = value.includes(option.value);

                    return (
                        <DropdownMenuCheckboxItem
                            key={String(option.value)}
                            checked={isChecked}
                            onSelect={(event) => event.preventDefault()}
                            onCheckedChange={(checked) =>
                                onChange(
                                    checked
                                        ? [...value, option.value]
                                        : value.filter(
                                              (item) => item !== option.value,
                                          ),
                                )
                            }
                            className={cn(isChecked && 'font-medium')}
                        >
                            {option.label}
                        </DropdownMenuCheckboxItem>
                    );
                })}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
