import { Head, router, useForm } from '@inertiajs/react';
import {
    ArrowDown,
    ArrowUp,
    CornerDownRight,
    FolderTree,
    Pencil,
    Plus,
    Trash2,
} from 'lucide-react';
import { useState } from 'react';
import { ConfirmAction } from '@/components/admin/confirm-action';
import { AdminPageHeader } from '@/components/admin/page-header';
import { TextField } from '@/components/admin/text-field';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useTranslation } from '@/hooks/use-translation';
import {
    destroy,
    index,
    move,
    store,
    update,
} from '@/routes/admin/ticket-categories';

type AdminCategory = {
    id: number;
    parent_id: number | null;
    name: string;
    description: string | null;
    ticket_form_id: number | null;
    is_visible_to_customers: boolean;
    is_active: boolean;
    children?: AdminCategory[];
};

type FormOption = { id: number; name: string; is_default: boolean };

type Editing =
    | { category: AdminCategory }
    | { category: null; parentId: number | null };

const INHERIT = '__inherit';
const TOP_LEVEL = '__top';

export default function TicketCategoriesIndex({
    categories,
    forms,
}: {
    categories: AdminCategory[];
    forms: FormOption[];
}) {
    const { t } = useTranslation();
    const [editing, setEditing] = useState<Editing | null>(null);
    const formName = (id: number | null) =>
        forms.find((form) => form.id === id)?.name;

    const row = (
        category: AdminCategory,
        position: number,
        siblings: number,
        parent: AdminCategory | null,
    ) => (
        <div
            className={
                parent
                    ? 'flex items-center gap-3 py-2 pr-4 pl-10'
                    : 'flex items-center gap-3 px-4 py-3'
            }
        >
            <div className="flex flex-col">
                {(['up', 'down'] as const).map((direction) => (
                    <button
                        key={direction}
                        type="button"
                        aria-label={
                            direction === 'up' ? t('Move up') : t('Move down')
                        }
                        disabled={
                            direction === 'up'
                                ? position === 0
                                : position === siblings - 1
                        }
                        onClick={() =>
                            router.post(
                                move.url(category.id),
                                { direction },
                                { preserveScroll: true },
                            )
                        }
                        className="rounded p-0.5 text-muted-foreground hover:bg-accent disabled:opacity-30"
                    >
                        {direction === 'up' ? (
                            <ArrowUp className="size-3.5" />
                        ) : (
                            <ArrowDown className="size-3.5" />
                        )}
                    </button>
                ))}
            </div>
            {parent && (
                <CornerDownRight className="size-4 shrink-0 text-muted-foreground" />
            )}
            <div className="min-w-0 flex-1">
                <p className="flex flex-wrap items-center gap-2 text-sm font-medium">
                    {category.name}
                    {!category.is_active && (
                        <Badge variant="outline">{t('Inactive')}</Badge>
                    )}
                    {!category.is_visible_to_customers && (
                        <Badge variant="secondary">{t('Agents only')}</Badge>
                    )}
                </p>
                <p className="truncate text-xs text-muted-foreground">
                    {category.ticket_form_id
                        ? t('Form: :name', {
                              name: formName(category.ticket_form_id) ?? '',
                          })
                        : parent
                          ? t('Uses the form of :name', { name: parent.name })
                          : t('Uses the default form')}
                </p>
            </div>
            {!parent && (
                <Button
                    variant="ghost"
                    size="sm"
                    onClick={() =>
                        setEditing({ category: null, parentId: category.id })
                    }
                >
                    <Plus /> {t('Subcategory')}
                </Button>
            )}
            <Button
                variant="ghost"
                size="icon"
                title={t('Edit')}
                onClick={() => setEditing({ category })}
            >
                <Pencil />
            </Button>
            <ConfirmAction
                trigger={
                    <Button variant="ghost" size="icon" title={t('Delete')}>
                        <Trash2 />
                    </Button>
                }
                title={t('Delete “:name”?', { name: category.name })}
                description={
                    category.children?.length
                        ? t(
                              'Its subcategories are deleted too. Tickets keep their fields but lose the category.',
                          )
                        : t('Tickets keep their fields but lose the category.')
                }
                href={destroy.url(category.id)}
            />
        </div>
    );

    return (
        <>
            <Head title={t('Categories')} />
            <AdminPageHeader
                title={t('Categories')}
                description={t(
                    'Requesters pick a category and subcategory first. The category decides which form is shown, and routing rules can use it to choose the group.',
                )}
                actions={
                    <Button
                        onClick={() =>
                            setEditing({ category: null, parentId: null })
                        }
                    >
                        <Plus /> {t('New category')}
                    </Button>
                }
            />

            {categories.length === 0 ? (
                <div className="rounded-xl border border-dashed px-6 py-16 text-center text-sm text-muted-foreground">
                    <FolderTree className="mx-auto mb-2 size-6" />
                    {t(
                        'No categories yet. Without categories, every ticket uses the default form.',
                    )}
                </div>
            ) : (
                <ul className="divide-y overflow-hidden rounded-xl border bg-card shadow-xs">
                    {categories.map((category, position) => (
                        <li key={category.id}>
                            {row(category, position, categories.length, null)}
                            {(category.children ?? []).length > 0 && (
                                <ul className="divide-y border-t bg-muted/30">
                                    {(category.children ?? []).map(
                                        (child, childPosition) => (
                                            <li key={child.id}>
                                                {row(
                                                    child,
                                                    childPosition,
                                                    category.children?.length ??
                                                        0,
                                                    category,
                                                )}
                                            </li>
                                        ),
                                    )}
                                </ul>
                            )}
                        </li>
                    ))}
                </ul>
            )}

            <Dialog
                open={editing !== null}
                onOpenChange={(open) => !open && setEditing(null)}
            >
                <DialogContent className="max-h-[90vh] overflow-y-auto">
                    {editing !== null && (
                        <CategoryForm
                            editing={editing}
                            categories={categories}
                            forms={forms}
                            onDone={() => setEditing(null)}
                        />
                    )}
                </DialogContent>
            </Dialog>
        </>
    );
}

function CategoryForm({
    editing,
    categories,
    forms,
    onDone,
}: {
    editing: Editing;
    categories: AdminCategory[];
    forms: FormOption[];
    onDone: () => void;
}) {
    const { t } = useTranslation();
    const category = editing.category;
    const form = useForm({
        name: category?.name ?? '',
        description: category?.description ?? '',
        parent_id: category ? category.parent_id : editing.parentId,
        ticket_form_id: category?.ticket_form_id ?? null,
        is_visible_to_customers: category?.is_visible_to_customers ?? true,
        is_active: category?.is_active ?? true,
    });
    const errors = form.errors as Record<string, string | undefined>;
    const parents = categories.filter((root) => root.id !== category?.id);
    const canHaveParent = !category?.children?.length;

    const submit = (event: React.FormEvent) => {
        event.preventDefault();

        const options = { preserveScroll: true, onSuccess: onDone };

        if (category) {
            form.put(update.url(category.id), options);
        } else {
            form.post(store.url(), options);
        }
    };

    return (
        <form onSubmit={submit} className="space-y-4">
            <DialogTitle>
                {category
                    ? t('Edit category')
                    : form.data.parent_id
                      ? t('New subcategory')
                      : t('New category')}
            </DialogTitle>
            <DialogDescription>
                {t(
                    'Requesters see the name and description when they submit a request.',
                )}
            </DialogDescription>

            <TextField
                id="category-name"
                label={t('Name')}
                value={form.data.name}
                onChange={(name) => form.setData('name', name)}
                error={errors.name}
                required
            />
            <TextField
                id="category-description"
                label={t('Description')}
                value={form.data.description}
                onChange={(description) =>
                    form.setData('description', description)
                }
                error={errors.description}
                multiline
            />

            <div className="grid gap-4 sm:grid-cols-2">
                <div className="grid gap-2">
                    <Label>{t('Parent category')}</Label>
                    <Select
                        value={
                            form.data.parent_id
                                ? String(form.data.parent_id)
                                : TOP_LEVEL
                        }
                        disabled={!canHaveParent}
                        onValueChange={(value) =>
                            form.setData(
                                'parent_id',
                                value === TOP_LEVEL ? null : Number(value),
                            )
                        }
                    >
                        <SelectTrigger className="w-full">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value={TOP_LEVEL}>
                                {t('None (top level)')}
                            </SelectItem>
                            {parents.map((parent) => (
                                <SelectItem
                                    key={parent.id}
                                    value={String(parent.id)}
                                >
                                    {parent.name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <InputError message={errors.parent_id} />
                </div>
                <div className="grid gap-2">
                    <Label>{t('Form')}</Label>
                    <Select
                        value={
                            form.data.ticket_form_id
                                ? String(form.data.ticket_form_id)
                                : INHERIT
                        }
                        onValueChange={(value) =>
                            form.setData(
                                'ticket_form_id',
                                value === INHERIT ? null : Number(value),
                            )
                        }
                    >
                        <SelectTrigger className="w-full">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value={INHERIT}>
                                {form.data.parent_id
                                    ? t('Same as parent category')
                                    : t('Default form')}
                            </SelectItem>
                            {forms.map((option) => (
                                <SelectItem
                                    key={option.id}
                                    value={String(option.id)}
                                >
                                    {option.name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <InputError message={errors.ticket_form_id} />
                </div>
            </div>

            <div className="flex flex-col gap-2 text-sm">
                <label className="flex items-center gap-2">
                    <input
                        type="checkbox"
                        checked={form.data.is_visible_to_customers}
                        onChange={(event) =>
                            form.setData(
                                'is_visible_to_customers',
                                event.target.checked,
                            )
                        }
                        className="size-4 accent-[var(--primary)]"
                    />
                    {t('Customers can pick it on the request form')}
                </label>
                <label className="flex items-center gap-2">
                    <input
                        type="checkbox"
                        checked={form.data.is_active}
                        onChange={(event) =>
                            form.setData('is_active', event.target.checked)
                        }
                        className="size-4 accent-[var(--primary)]"
                    />
                    {t('Active')}
                </label>
            </div>

            <div className="flex justify-end">
                <Button type="submit" disabled={form.processing}>
                    {t('Save')}
                </Button>
            </div>
        </form>
    );
}

TicketCategoriesIndex.layout = {
    breadcrumbs: [
        { title: 'Admin center', href: '/admin' },
        { title: 'Categories', href: index() },
    ],
};
