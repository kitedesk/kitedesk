import { usePage } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { useState } from 'react';
import type { NodeCategory } from '@/components/workflows/node-catalog';
import {
    CATEGORY_LABELS,
    CATEGORY_STYLES,
    NODE_DEFINITIONS,
} from '@/components/workflows/node-catalog';
import { Input } from '@/components/ui/input';
import { useTranslation } from '@/hooks/use-translation';
import { cn } from '@/lib/utils';
import type { WorkflowNodeType, WorkflowOptions } from '@/types';

export const DRAG_TYPE = 'application/x-kitedesk-workflow-node';

/**
 * The steps that can be added, grouped and searchable. Drag one onto the canvas, or click it.
 */
export function NodePalette({
    onPick,
    autoFocus = false,
    draggable = true,
}: {
    onPick: (type: WorkflowNodeType) => void;
    autoFocus?: boolean;
    draggable?: boolean;
}) {
    const { t } = useTranslation();
    const [query, setQuery] = useState('');
    const term = query.trim().toLowerCase();

    const aiAvailable =
        usePage<{ options?: WorkflowOptions }>().props.options?.aiAvailable ??
        false;

    const groups = (['logic', 'action', 'ai'] as NodeCategory[]).map(
        (category) => ({
            category,
            items: Object.values(NODE_DEFINITIONS).filter(
                (definition) =>
                    definition.category === category &&
                    (term === '' ||
                        t(definition.label).toLowerCase().includes(term) ||
                        t(definition.description).toLowerCase().includes(term)),
            ),
        }),
    );

    return (
        <div className="flex min-h-0 flex-1 flex-col gap-3">
            <div className="relative">
                <Search className="absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground" />
                <Input
                    value={query}
                    onChange={(event) => setQuery(event.target.value)}
                    placeholder={t('Search steps…')}
                    className="pl-8"
                    autoFocus={autoFocus}
                />
            </div>
            <div className="-mx-1 min-h-0 flex-1 space-y-4 overflow-y-auto px-1">
                {groups
                    .filter((group) => group.items.length > 0)
                    .map((group) => (
                        <section key={group.category} className="space-y-1">
                            <h3 className="px-1 text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                {t(CATEGORY_LABELS[group.category])}
                            </h3>
                            {group.category === 'ai' && !aiAvailable && (
                                <p className="px-1 text-xs text-muted-foreground">
                                    {t(
                                        'Set up the AI assistant in Admin center → AI assistant first. Until then, these steps continue from “failed”.',
                                    )}
                                </p>
                            )}
                            {group.items.map((definition) => (
                                <button
                                    key={definition.type}
                                    type="button"
                                    draggable={draggable}
                                    onDragStart={(event) => {
                                        event.dataTransfer.setData(
                                            DRAG_TYPE,
                                            definition.type,
                                        );
                                        event.dataTransfer.effectAllowed =
                                            'move';
                                    }}
                                    onClick={() => onPick(definition.type)}
                                    className="flex w-full cursor-grab items-center gap-2.5 rounded-lg border border-transparent p-2 text-left transition-colors hover:border-border hover:bg-accent active:cursor-grabbing"
                                >
                                    <span
                                        className={cn(
                                            'flex size-8 shrink-0 items-center justify-center rounded-md',
                                            CATEGORY_STYLES[
                                                definition.category
                                            ],
                                        )}
                                    >
                                        <definition.icon className="size-4" />
                                    </span>
                                    <span className="min-w-0">
                                        <span className="block text-sm font-medium">
                                            {t(definition.label)}
                                        </span>
                                        <span className="block truncate text-xs text-muted-foreground">
                                            {t(definition.description)}
                                        </span>
                                    </span>
                                </button>
                            ))}
                        </section>
                    ))}
            </div>
        </div>
    );
}
