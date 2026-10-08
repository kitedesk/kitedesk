import { ReactRenderer } from '@tiptap/react';
import type { SuggestionOptions, SuggestionProps } from '@tiptap/suggestion';
import type { LucideIcon } from 'lucide-react';
import { useEffect, useImperativeHandle, useRef, useState } from 'react';
import type { Ref } from 'react';
import { t } from '@/lib/i18n';
import { cn } from '@/lib/utils';

/**
 * One row in an editor suggestion popup (@mention, #ticket, /canned response).
 */
export type SuggestionEntry<T = unknown> = {
    key: string;
    title: string;
    hint?: string;
    icon: LucideIcon;
    value: T;
};

type ListHandle = {
    onKeyDown: (event: KeyboardEvent) => boolean;
};

type ListProps = {
    items: SuggestionEntry[];
    loading: boolean;
    emptyText: string;
    onSelect: (entry: SuggestionEntry) => void;
    ref?: Ref<ListHandle>;
};

function SuggestionList({
    items,
    loading,
    emptyText,
    onSelect,
    ref,
}: ListProps) {
    const [active, setActive] = useState(0);
    const list = useRef<HTMLDivElement>(null);

    useEffect(() => setActive(0), [items]);

    useEffect(() => {
        list.current
            ?.querySelector(`[data-index="${active}"]`)
            ?.scrollIntoView({ block: 'nearest' });
    }, [active]);

    useImperativeHandle(
        ref,
        () => ({
            onKeyDown: (event) => {
                if (items.length === 0) {
                    return false;
                }

                if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                    const step = event.key === 'ArrowDown' ? 1 : -1;
                    setActive(
                        (current) =>
                            (current + step + items.length) % items.length,
                    );

                    return true;
                }

                if (event.key === 'Enter' || event.key === 'Tab') {
                    onSelect(items[active] ?? items[0]);

                    return true;
                }

                return false;
            },
        }),
        [items, active, onSelect],
    );

    return (
        <div
            ref={list}
            role="listbox"
            className="z-50 max-h-64 w-72 overflow-y-auto rounded-lg border bg-popover p-1 text-popover-foreground shadow-lg"
        >
            {items.length === 0 ? (
                <p className="px-2 py-1.5 text-sm text-muted-foreground">
                    {loading ? t('Loading…') : emptyText}
                </p>
            ) : (
                items.map((entry, index) => (
                    <button
                        key={entry.key}
                        type="button"
                        role="option"
                        data-index={index}
                        aria-selected={index === active}
                        // Keep focus (and the cursor) in the editor.
                        onMouseDown={(event) => event.preventDefault()}
                        onMouseEnter={() => setActive(index)}
                        onClick={() => onSelect(entry)}
                        className={cn(
                            'flex w-full items-center gap-2 rounded-md px-2 py-1.5 text-left text-sm',
                            index === active && 'bg-accent text-foreground',
                        )}
                    >
                        <entry.icon className="size-4 shrink-0 text-muted-foreground" />
                        <span className="min-w-0 flex-1 truncate">
                            {entry.title}
                        </span>
                        {entry.hint && (
                            <span className="max-w-28 shrink-0 truncate text-xs text-muted-foreground">
                                {entry.hint}
                            </span>
                        )}
                    </button>
                ))
            )}
        </div>
    );
}

/**
 * Tiptap suggestion `render` that shows a keyboard-driven list at the cursor.
 * Tiptap positions it (Floating UI) and handles Escape and outside clicks.
 */
export function suggestionPopup(
    emptyText: () => string,
): NonNullable<SuggestionOptions<SuggestionEntry>['render']> {
    return () => {
        let renderer: ReactRenderer<ListHandle, ListProps> | null = null;
        let unmount: (() => void) | null = null;

        const listProps = (
            props: SuggestionProps<SuggestionEntry>,
        ): ListProps => ({
            items: props.items,
            loading: props.loading,
            emptyText: emptyText(),
            onSelect: (entry) => props.command(entry),
        });

        return {
            onStart: (props) => {
                renderer = new ReactRenderer(SuggestionList, {
                    editor: props.editor,
                    props: listProps(props),
                });
                unmount = props.mount(renderer.element);
            },
            onUpdate: (props) => renderer?.updateProps(listProps(props)),
            onKeyDown: ({ event }) => renderer?.ref?.onKeyDown(event) ?? false,
            onExit: () => {
                unmount?.();
                renderer?.destroy();
                renderer = null;
                unmount = null;
            },
        };
    };
}
