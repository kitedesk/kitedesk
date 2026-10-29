import { Check } from 'lucide-react';
import { useTranslation } from '@/hooks/use-translation';
import { cn } from '@/lib/utils';

export type SidebarMode = 'menus' | 'legacy';

/**
 * The two sidebar layouts as cards to pick from. `extra` adds an option such as "follow the default".
 */
export function SidebarStyleChoice({
    value,
    onChange,
    extra,
}: {
    value: string;
    onChange: (value: string) => void;
    extra?: { value: string; title: string; description: string };
}) {
    const { t } = useTranslation();
    const options = [
        ...(extra ? [extra] : []),
        {
            value: 'menus',
            title: t('Menus and submenus'),
            description: t(
                'Ticket views and each module’s pages are grouped into menus you can open and close.',
            ),
        },
        {
            value: 'legacy',
            title: t('Classic list'),
            description: t(
                'Views, saved views and statuses listed one after the other, with the modules’ pages below.',
            ),
        },
    ];

    return (
        <div role="radiogroup" className="grid gap-2">
            {options.map((option) => {
                const selected = option.value === value;

                return (
                    <button
                        key={option.value}
                        type="button"
                        role="radio"
                        aria-checked={selected}
                        onClick={() => onChange(option.value)}
                        className={cn(
                            'flex items-start gap-3 rounded-lg border p-3 text-left transition-colors hover:bg-accent/40 focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none',
                            selected && 'border-primary bg-accent/40',
                        )}
                    >
                        <span
                            className={cn(
                                'mt-0.5 flex size-4 shrink-0 items-center justify-center rounded-full border',
                                selected &&
                                    'border-primary bg-primary text-primary-foreground',
                            )}
                        >
                            {selected && <Check className="size-3" />}
                        </span>
                        <span className="space-y-0.5">
                            <span className="block text-sm font-medium">
                                {option.title}
                            </span>
                            <span className="block text-sm text-muted-foreground">
                                {option.description}
                            </span>
                        </span>
                    </button>
                );
            })}
        </div>
    );
}
