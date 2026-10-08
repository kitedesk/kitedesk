import { useState } from 'react';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useTranslation } from '@/hooks/use-translation';
import type { CategoryNode } from '@/types';

const NONE = '__none';

/**
 * Find a category or subcategory by id, with its parent when it is a subcategory.
 */
export function findCategory(
    categories: CategoryNode[],
    id: number | null | undefined,
): { category: CategoryNode; parent: CategoryNode | null } | null {
    for (const category of categories) {
        if (category.id === id) {
            return { category, parent: null };
        }

        const child = category.children?.find((node) => node.id === id);

        if (child) {
            return { category: child, parent: category };
        }
    }

    return null;
}

/**
 * Category → subcategory picker. The value is the most specific category chosen;
 * picking a category that has subcategories asks for one of them next.
 */
export function CategorySelect({
    categories,
    value,
    onChange,
    disabled,
    allowNone = false,
    layout = 'stacked',
    errors,
}: {
    categories: CategoryNode[];
    value: number | null;
    onChange: (categoryId: number | null) => void;
    disabled?: boolean;
    /** Agents may leave a ticket uncategorized. */
    allowNone?: boolean;
    layout?: 'stacked' | 'inline';
    errors?: string;
}) {
    const { t } = useTranslation();
    // A category picked while its subcategory is still to be chosen (the value may lag behind).
    const [pendingRootId, setPendingRootId] = useState<number | null>(null);
    const selected = findCategory(categories, value);
    const root =
        categories.find((category) => category.id === pendingRootId) ??
        selected?.parent ??
        selected?.category ??
        null;
    const children = root?.children ?? [];
    const subcategory =
        selected?.parent && selected.parent.id === root?.id
            ? selected.category
            : null;

    return (
        <div
            className={
                layout === 'inline' ? 'grid gap-2' : 'grid gap-4 sm:grid-cols-2'
            }
        >
            <div className="grid gap-2">
                {layout === 'stacked' && (
                    <Label htmlFor="category">{t('Category')}</Label>
                )}
                <Select
                    value={root ? String(root.id) : allowNone ? NONE : ''}
                    disabled={disabled}
                    onValueChange={(id) => {
                        const categoryId = id === NONE ? null : Number(id);
                        const node = categories.find(
                            (category) => category.id === categoryId,
                        );

                        setPendingRootId(
                            node?.children?.length ? node.id : null,
                        );
                        onChange(categoryId);
                    }}
                >
                    <SelectTrigger id="category" className="h-9 w-full">
                        <SelectValue placeholder={t('Choose a category…')} />
                    </SelectTrigger>
                    <SelectContent>
                        {allowNone && (
                            <SelectItem value={NONE}>
                                {t('No category')}
                            </SelectItem>
                        )}
                        {categories.map((category) => (
                            <SelectItem
                                key={category.id}
                                value={String(category.id)}
                            >
                                {category.name}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                {root?.description && layout === 'stacked' && (
                    <p className="text-xs text-muted-foreground">
                        {root.description}
                    </p>
                )}
            </div>

            {children.length > 0 && (
                <div className="grid gap-2">
                    {layout === 'stacked' && (
                        <Label htmlFor="subcategory">{t('Subcategory')}</Label>
                    )}
                    <Select
                        value={subcategory ? String(subcategory.id) : ''}
                        disabled={disabled}
                        onValueChange={(id) => {
                            setPendingRootId(null);
                            onChange(Number(id));
                        }}
                    >
                        <SelectTrigger id="subcategory" className="h-9 w-full">
                            <SelectValue
                                placeholder={t('Choose a subcategory…')}
                            />
                        </SelectTrigger>
                        <SelectContent>
                            {children.map((child) => (
                                <SelectItem
                                    key={child.id}
                                    value={String(child.id)}
                                >
                                    {child.name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    {subcategory?.description && layout === 'stacked' && (
                        <p className="text-xs text-muted-foreground">
                            {subcategory.description}
                        </p>
                    )}
                </div>
            )}

            {errors && (
                <p className="text-sm text-destructive sm:col-span-2">
                    {errors}
                </p>
            )}
        </div>
    );
}
