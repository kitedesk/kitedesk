import { router, useForm, usePage } from '@inertiajs/react';
import {
    ArrowDown,
    ArrowUp,
    BookmarkPlus,
    Settings2,
    Trash2,
    UsersRound,
} from 'lucide-react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { usePermissions } from '@/hooks/use-permissions';
import { useTranslation } from '@/hooks/use-translation';
import { priorityLabel, statusLabel } from '@/lib/tickets';
import { destroy, move, store, update } from '@/routes/agent/views';
import type {
    TicketOptions,
    TicketPriority,
    TicketsLayout,
    TicketStatus,
} from '@/types';

const DEFAULT_LAYOUT = '__default';

export type SavedView = {
    id: number;
    name: string;
    filters: Record<string, string>;
    layout: TicketsLayout | null;
    is_shared: boolean;
    manageable: boolean;
};

/**
 * "Shared view · Priority: High · Assignee: Me" under the queue title, since a saved
 * view's own filters don't show in the filter controls.
 */
export function SavedViewSummary({
    savedView,
    options,
}: {
    savedView: SavedView;
    options: TicketOptions;
}) {
    const { t } = useTranslation();
    const { filters } = savedView;
    const named = (items: { id: number; name: string }[], id?: string) =>
        items.find((item) => String(item.id) === id)?.name ?? id ?? '';
    const categoryName = (id?: string) => {
        for (const category of options.categories) {
            if (String(category.id) === id) {
                return category.name;
            }

            const child = category.children?.find(
                (node) => String(node.id) === id,
            );

            if (child) {
                return `${category.name} › ${child.name}`;
            }
        }

        return id ?? '';
    };

    const parts = [
        filters.ticket_status_id &&
            `${t('Status')}: ${named(options.customStatuses, filters.ticket_status_id)}`,
        filters.status &&
            `${t('Status')}: ${statusLabel(filters.status as TicketStatus)}`,
        filters.priority &&
            `${t('Priority')}: ${priorityLabel(filters.priority as TicketPriority)}`,
        filters.assignee_id &&
            `${t('Assignee')}: ${filters.assignee_id === 'me' ? t('Me') : named(options.agents, filters.assignee_id)}`,
        filters.group_id &&
            `${t('Group')}: ${named(options.groups, filters.group_id)}`,
        filters.category_id &&
            `${t('Category')}: ${categoryName(filters.category_id)}`,
        filters.tag && `${t('Tag')}: ${filters.tag}`,
        filters.search && `${t('Search')}: “${filters.search}”`,
    ].filter(Boolean);

    return (
        <p className="mt-0.5 text-xs text-muted-foreground">
            {savedView.is_shared ? t('Shared view') : t('Your saved view')}
            {parts.length > 0 && ` · ${parts.join(' · ')}`}
        </p>
    );
}

/**
 * Save the queue's current view, filters and sort under a name.
 */
export function SaveViewButton({
    view,
    filter,
    sort,
    layout,
}: {
    view: string;
    filter: Record<string, string>;
    sort?: string;
    /** Saved with the view, so it opens the way it was saved. */
    layout?: TicketsLayout;
}) {
    const { t } = useTranslation();
    const { can } = usePermissions();
    const [open, setOpen] = useState(false);
    const form = useForm({ name: '', is_shared: false });

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        form.transform((data) => ({ ...data, view, filter, sort, layout }));
        form.post(store.url(), {
            onSuccess: () => {
                form.reset();
                setOpen(false);
            },
        });
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button variant="ghost" size="sm">
                    <BookmarkPlus /> {t('Save view')}
                </Button>
            </DialogTrigger>
            <DialogContent>
                <form onSubmit={submit} className="space-y-4">
                    <DialogTitle>{t('Save view')}</DialogTitle>
                    <DialogDescription>
                        {t(
                            'Keep this list and its filters one click away in the sidebar.',
                        )}
                    </DialogDescription>
                    <div className="grid gap-2">
                        <Label htmlFor="view-name">{t('Name')}</Label>
                        <Input
                            id="view-name"
                            value={form.data.name}
                            onChange={(event) =>
                                form.setData('name', event.target.value)
                            }
                            placeholder={t('e.g. Urgent billing tickets')}
                            required
                            autoFocus
                        />
                        <InputError message={form.errors.name} />
                    </div>
                    {can('views.share') && (
                        <label className="flex items-center gap-2 text-sm">
                            <input
                                type="checkbox"
                                checked={form.data.is_shared}
                                onChange={(event) =>
                                    form.setData(
                                        'is_shared',
                                        event.target.checked,
                                    )
                                }
                                className="size-4 accent-[var(--primary)]"
                            />
                            {t('Share with all agents')}
                        </label>
                    )}
                    <DialogFooter>
                        <Button type="submit" disabled={form.processing}>
                            {t('Save view')}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

/**
 * Rename, reorder or delete the saved views the current user may manage.
 */
export function ManageViewsButton() {
    const { t } = useTranslation();
    const { agentNav } = usePage().props;
    const views = (agentNav?.views ?? []).filter(
        (view) => view.saved?.manageable,
    );

    if (views.length === 0) {
        return null;
    }

    const options = { preserveScroll: true, preserveState: true };

    return (
        <Dialog>
            <DialogTrigger asChild>
                <Button variant="ghost" size="sm">
                    <Settings2 /> {t('Manage views')}
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>{t('Manage views')}</DialogTitle>
                <DialogDescription>
                    {t(
                        'Rename, reorder or delete your saved views, and pick how each one opens.',
                    )}
                </DialogDescription>
                <ul className="divide-y rounded-lg border">
                    {views.map((view, position) => {
                        const id = view.saved!.id;
                        const siblings = views.filter(
                            (other) =>
                                other.saved?.shared === view.saved?.shared,
                        );
                        const siblingIndex = siblings.indexOf(view);

                        return (
                            <li
                                key={view.key}
                                className="flex items-center gap-2 px-2 py-1.5"
                            >
                                <div className="flex flex-col">
                                    <button
                                        type="button"
                                        aria-label={t('Move up')}
                                        disabled={siblingIndex === 0}
                                        onClick={() =>
                                            router.post(
                                                move.url(id),
                                                { direction: 'up' },
                                                options,
                                            )
                                        }
                                        className="rounded p-0.5 text-muted-foreground hover:bg-accent disabled:opacity-30"
                                    >
                                        <ArrowUp className="size-3.5" />
                                    </button>
                                    <button
                                        type="button"
                                        aria-label={t('Move down')}
                                        disabled={
                                            siblingIndex === siblings.length - 1
                                        }
                                        onClick={() =>
                                            router.post(
                                                move.url(id),
                                                { direction: 'down' },
                                                options,
                                            )
                                        }
                                        className="rounded p-0.5 text-muted-foreground hover:bg-accent disabled:opacity-30"
                                    >
                                        <ArrowDown className="size-3.5" />
                                    </button>
                                </div>
                                {view.saved?.shared && (
                                    <UsersRound
                                        className="size-4 shrink-0 text-muted-foreground"
                                        aria-label={t('Shared')}
                                    />
                                )}
                                <Input
                                    key={`${view.key}-${view.label}-${position}`}
                                    defaultValue={view.label}
                                    aria-label={t('Name')}
                                    className="h-8"
                                    onBlur={(event) => {
                                        const name = event.target.value.trim();

                                        if (name && name !== view.label) {
                                            router.patch(
                                                update.url(id),
                                                { name },
                                                options,
                                            );
                                        }
                                    }}
                                />
                                <Select
                                    value={view.saved?.layout ?? DEFAULT_LAYOUT}
                                    onValueChange={(layout) =>
                                        router.patch(
                                            update.url(id),
                                            {
                                                layout:
                                                    layout === DEFAULT_LAYOUT
                                                        ? null
                                                        : layout,
                                            },
                                            options,
                                        )
                                    }
                                >
                                    <SelectTrigger
                                        size="sm"
                                        aria-label={t('Layout')}
                                        className="h-8 w-28 shrink-0 text-xs"
                                    >
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value={DEFAULT_LAYOUT}>
                                            {t('My default')}
                                        </SelectItem>
                                        <SelectItem value="list">
                                            {t('List')}
                                        </SelectItem>
                                        <SelectItem value="board">
                                            {t('Board')}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    aria-label={t('Delete :name', {
                                        name: view.label,
                                    })}
                                    onClick={() =>
                                        router.delete(destroy.url(id), options)
                                    }
                                >
                                    <Trash2 />
                                </Button>
                            </li>
                        );
                    })}
                </ul>
            </DialogContent>
        </Dialog>
    );
}
