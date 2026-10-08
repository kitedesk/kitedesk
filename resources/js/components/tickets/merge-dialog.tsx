import { router } from '@inertiajs/react';
import { GitMerge } from 'lucide-react';
import { useState } from 'react';
import { TicketSearch } from '@/components/tickets/ticket-search';
import type { TicketMatch } from '@/components/tickets/ticket-search';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { useTranslation } from '@/hooks/use-translation';
import { merge } from '@/routes/agent/tickets';

/**
 * Merge this ticket (a duplicate) into another one.
 */
export function MergeDialog({
    ticketId,
    ticketNumber,
    requesterId,
}: {
    ticketId: number;
    ticketNumber: string;
    requesterId: number;
}) {
    const { t } = useTranslation();
    const [open, setOpen] = useState(false);
    const [target, setTarget] = useState<TicketMatch | null>(null);
    const [processing, setProcessing] = useState(false);

    return (
        <Dialog
            open={open}
            onOpenChange={(next) => {
                setOpen(next);
                setTarget(null);
            }}
        >
            <DialogTrigger asChild>
                <Button variant="outline" size="sm">
                    <GitMerge /> {t('Merge')}
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>
                    {t('Merge :number into another ticket', {
                        number: ticketNumber,
                    })}
                </DialogTitle>
                <DialogDescription>
                    {t(
                        'The conversation moves to the other ticket, this requester is copied on it, and this ticket is closed.',
                    )}
                </DialogDescription>

                {target ? (
                    <div className="space-y-2">
                        <p className="rounded-md border bg-muted/40 px-3 py-2 text-sm">
                            {t('Merge into :number :subject', {
                                number: target.number,
                                subject: target.subject,
                            })}
                        </p>
                        {target.requester_id !== requesterId && (
                            <p className="rounded-md border border-amber-500/40 bg-amber-500/10 px-3 py-2 text-sm text-amber-700 dark:text-amber-400">
                                {t(
                                    'That ticket belongs to someone else. Merging copies this requester on it, so they will see its public conversation.',
                                )}
                            </p>
                        )}
                    </div>
                ) : (
                    <TicketSearch
                        excludeIds={[ticketId]}
                        onSelect={setTarget}
                        autoFocus
                    />
                )}

                <DialogFooter className="gap-2">
                    {target && (
                        <Button variant="ghost" onClick={() => setTarget(null)}>
                            {t('Pick another')}
                        </Button>
                    )}
                    <Button
                        disabled={!target || processing}
                        onClick={() =>
                            target &&
                            router.post(
                                merge.url(ticketId),
                                { target_id: target.id },
                                {
                                    onStart: () => setProcessing(true),
                                    onFinish: () => {
                                        setProcessing(false);
                                        setOpen(false);
                                    },
                                },
                            )
                        }
                    >
                        <GitMerge /> {t('Merge tickets')}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
