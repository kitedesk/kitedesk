/**
 * Adapted from EasyUI's CommandMenu (MIT, github.com/Surajmaurya1/easyui).
 * Changes: items are passed in (instead of EasyUI's docs catalog), colors use the
 * app's theme tokens, and an optional `onQueryChange` lets callers load remote results.
 */
import { AnimatePresence, m } from 'framer-motion';
import { CornerDownLeft, Search } from 'lucide-react';
import React, { useEffect, useMemo, useState } from 'react';
import { useTranslation } from '@/hooks/use-translation';
import { motionTransitions } from '@/lib/motion-tokens';
import { cn } from '@/lib/utils';

export interface CommandItem {
    id: string;
    title: string;
    category: string;
    icon?: React.ReactNode;
    hint?: string;
    shortcut?: string;
    onSelect: () => void;
}

export interface CommandMenuProps {
    isOpen: boolean;
    onClose: () => void;
    items: CommandItem[];
    placeholder?: string;
    /** Called with the raw query so callers can fetch remote results (debounce on the caller side). */
    onQueryChange?: (query: string) => void;
    /** Skip local filtering for items whose category is listed here (already filtered remotely). */
    remoteCategories?: string[];
    footerLabel?: string;
}

export function CommandMenu({
    isOpen,
    onClose,
    items,
    placeholder,
    onQueryChange,
    remoteCategories = [],
    footerLabel,
}: CommandMenuProps) {
    const { t } = useTranslation();
    const [query, setQuery] = useState('');
    const [selectedIndex, setSelectedIndex] = useState(0);

    const filteredItems = useMemo(() => {
        const needle = query.trim().toLowerCase();

        return items.filter(
            (item) =>
                remoteCategories.includes(item.category) ||
                needle === '' ||
                item.title.toLowerCase().includes(needle) ||
                item.category.toLowerCase().includes(needle) ||
                (item.hint ?? '').toLowerCase().includes(needle),
        );
    }, [items, query, remoteCategories]);

    useEffect(() => {
        if (!isOpen) {
            return;
        }

        const handleKeyDown = (e: KeyboardEvent) => {
            if (e.key === 'Escape') {
                onClose();
            } else if (e.key === 'ArrowDown') {
                e.preventDefault();
                setSelectedIndex(
                    (prev) => (prev + 1) % (filteredItems.length || 1),
                );
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                setSelectedIndex(
                    (prev) =>
                        (prev - 1 + filteredItems.length) %
                        (filteredItems.length || 1),
                );
            } else if (e.key === 'Enter' && filteredItems[selectedIndex]) {
                e.preventDefault();
                filteredItems[selectedIndex].onSelect();
                onClose();
            }
        };

        window.addEventListener('keydown', handleKeyDown);

        return () => window.removeEventListener('keydown', handleKeyDown);
    }, [isOpen, filteredItems, selectedIndex, onClose]);

    return (
        <AnimatePresence
            onExitComplete={() => {
                setQuery('');
                setSelectedIndex(0);
            }}
        >
            {isOpen && (
                <div
                    className="fixed inset-0 z-50 flex items-start justify-center px-4 pt-20 sm:px-6"
                    role="dialog"
                    aria-modal="true"
                    aria-label={t('Command palette')}
                >
                    <m.div
                        initial={{ opacity: 0 }}
                        animate={{ opacity: 1 }}
                        exit={{ opacity: 0 }}
                        onClick={onClose}
                        className="fixed inset-0 bg-black/40 backdrop-blur-sm dark:bg-black/60"
                    />

                    <m.div
                        initial={{ opacity: 0, scale: 0.97, y: -8 }}
                        animate={{ opacity: 1, scale: 1, y: 0 }}
                        exit={{ opacity: 0, scale: 0.97, y: -8 }}
                        transition={motionTransitions.springSnappy}
                        className="relative z-10 w-full max-w-xl overflow-hidden rounded-xl border bg-popover text-popover-foreground shadow-2xl"
                    >
                        <div className="flex items-center border-b px-4 py-3">
                            <Search className="mr-2.5 size-4 shrink-0 text-muted-foreground" />
                            <input
                                autoFocus
                                type="text"
                                value={query}
                                onChange={(e) => {
                                    setQuery(e.target.value);
                                    setSelectedIndex(0);
                                    onQueryChange?.(e.target.value);
                                }}
                                placeholder={
                                    placeholder ??
                                    t('Type a command or search…')
                                }
                                className="w-full bg-transparent text-base placeholder:text-muted-foreground focus:outline-none"
                            />
                            <kbd className="rounded border bg-muted px-1.5 py-0.5 font-mono text-[10px] text-muted-foreground">
                                ESC
                            </kbd>
                        </div>

                        <div
                            className="max-h-80 overflow-y-auto p-1.5"
                            role="listbox"
                        >
                            {filteredItems.length === 0 ? (
                                <div className="py-8 text-center text-xs text-muted-foreground">
                                    {t('Nothing matches “:query”', { query })}
                                </div>
                            ) : (
                                <div className="space-y-0.5">
                                    {filteredItems.map((item, idx) => {
                                        const isSelected =
                                            idx === selectedIndex;

                                        return (
                                            <button
                                                key={item.id}
                                                type="button"
                                                onClick={() => {
                                                    item.onSelect();
                                                    onClose();
                                                }}
                                                onMouseEnter={() =>
                                                    setSelectedIndex(idx)
                                                }
                                                role="option"
                                                aria-selected={isSelected}
                                                className={cn(
                                                    'flex w-full items-center justify-between gap-3 rounded-md px-3 py-2 text-left text-sm transition-colors',
                                                    isSelected
                                                        ? 'bg-accent text-accent-foreground'
                                                        : 'text-muted-foreground',
                                                )}
                                            >
                                                <div className="flex min-w-0 items-center gap-2.5">
                                                    {item.icon}
                                                    <span className="truncate">
                                                        {item.title}
                                                    </span>
                                                    <span className="shrink-0 text-[11px] text-muted-foreground/70">
                                                        {item.category}
                                                    </span>
                                                </div>
                                                <div className="flex shrink-0 items-center gap-2">
                                                    {isSelected && (
                                                        <CornerDownLeft className="size-3" />
                                                    )}
                                                    {item.shortcut && (
                                                        <kbd className="rounded border bg-muted px-1.5 py-0.5 font-mono text-[10px]">
                                                            {item.shortcut}
                                                        </kbd>
                                                    )}
                                                </div>
                                            </button>
                                        );
                                    })}
                                </div>
                            )}
                        </div>

                        <div className="flex items-center justify-between border-t px-4 py-2 text-[11px] text-muted-foreground">
                            <div className="flex items-center gap-3">
                                <span className="flex items-center gap-1">
                                    <kbd className="rounded border bg-muted px-1 font-mono text-[10px]">
                                        ↑↓
                                    </kbd>
                                    {t('navigate')}
                                </span>
                                <span className="flex items-center gap-1">
                                    <kbd className="rounded border bg-muted px-1 font-mono text-[10px]">
                                        ↵
                                    </kbd>
                                    {t('select')}
                                </span>
                            </div>
                            {footerLabel && <span>{footerLabel}</span>}
                        </div>
                    </m.div>
                </div>
            )}
        </AnimatePresence>
    );
}
