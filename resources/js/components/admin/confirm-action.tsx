import { router } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { useTranslation } from '@/hooks/use-translation';

/**
 * Asks for confirmation before a destructive request.
 */
export function ConfirmAction({
    trigger,
    title,
    description,
    confirmLabel,
    href,
    method = 'delete',
}: {
    trigger: ReactNode;
    title: string;
    description: string;
    confirmLabel?: string;
    href: string;
    method?: 'delete' | 'post';
}) {
    const { t } = useTranslation();
    const [open, setOpen] = useState(false);
    const [processing, setProcessing] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>{trigger}</DialogTrigger>
            <DialogContent>
                <DialogTitle>{title}</DialogTitle>
                <DialogDescription>{description}</DialogDescription>
                <DialogFooter className="gap-2">
                    <DialogClose asChild>
                        <Button variant="secondary">{t('Cancel')}</Button>
                    </DialogClose>
                    <Button
                        variant="destructive"
                        disabled={processing}
                        onClick={() =>
                            router.visit(href, {
                                method,
                                preserveScroll: true,
                                onStart: () => setProcessing(true),
                                onFinish: () => {
                                    setProcessing(false);
                                    setOpen(false);
                                },
                            })
                        }
                    >
                        {confirmLabel ?? t('Delete')}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
