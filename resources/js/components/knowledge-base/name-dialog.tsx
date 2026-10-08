import { useForm } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTranslation } from '@/hooks/use-translation';

type Values = { name: string; slug: string; description: string };

/**
 * Create / rename dialog shared by help center categories and sections.
 */
export function NameDialog({
    trigger,
    title,
    description,
    action,
    method,
    initial,
    extra = {},
    submitLabel,
}: {
    trigger: ReactNode;
    title: string;
    description?: string;
    action: string;
    method: 'post' | 'patch';
    initial?: Partial<Values>;
    extra?: Record<string, string | number>;
    submitLabel?: string;
}) {
    const { t } = useTranslation();
    const [open, setOpen] = useState(false);
    const form = useForm<Values>({
        name: initial?.name ?? '',
        slug: initial?.slug ?? '',
        description: initial?.description ?? '',
    });

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        form.transform((data) => ({ ...data, ...extra }));
        form.submit(method, action, {
            preserveScroll: true,
            onSuccess: () => {
                setOpen(false);

                if (method === 'post') {
                    form.reset();
                }
            },
        });
    };

    return (
        <Dialog
            open={open}
            onOpenChange={(next) => {
                setOpen(next);

                if (!next) {
                    form.clearErrors();
                }
            }}
        >
            <DialogTrigger asChild>{trigger}</DialogTrigger>
            <DialogContent>
                <form onSubmit={submit} className="space-y-4">
                    <DialogHeader>
                        <DialogTitle>{title}</DialogTitle>
                        {description && (
                            <DialogDescription>{description}</DialogDescription>
                        )}
                    </DialogHeader>

                    <div className="grid gap-2">
                        <Label htmlFor="kb-name">{t('Name')}</Label>
                        <Input
                            id="kb-name"
                            value={form.data.name}
                            onChange={(event) =>
                                form.setData('name', event.target.value)
                            }
                            autoFocus
                        />
                        <InputError message={form.errors.name} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="kb-slug">
                            {t('Slug')}{' '}
                            <span className="font-normal text-muted-foreground">
                                {t('(optional, generated from the name)')}
                            </span>
                        </Label>
                        <Input
                            id="kb-slug"
                            value={form.data.slug}
                            onChange={(event) =>
                                form.setData('slug', event.target.value)
                            }
                        />
                        <InputError message={form.errors.slug} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="kb-description">
                            {t('Description')}
                        </Label>
                        <textarea
                            id="kb-description"
                            rows={3}
                            value={form.data.description}
                            onChange={(event) =>
                                form.setData('description', event.target.value)
                            }
                            className="rounded-md border bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30"
                        />
                        <InputError message={form.errors.description} />
                    </div>

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="ghost"
                            onClick={() => setOpen(false)}
                        >
                            {t('Cancel')}
                        </Button>
                        <Button type="submit" disabled={form.processing}>
                            {submitLabel ?? t('Save')}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
