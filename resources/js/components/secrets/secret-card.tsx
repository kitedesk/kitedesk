import { router, usePage } from '@inertiajs/react';
import { Copy, Eye, KeyRound, Link2, ShieldCheck, Trash2 } from 'lucide-react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import { ConfirmAction } from '@/components/admin/confirm-action';
import { SecretForm } from '@/components/secrets/secret-form';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { useClipboard } from '@/hooks/use-clipboard';
import { useTranslation } from '@/hooks/use-translation';
import { postJson } from '@/lib/post-json';
import { relativeTime } from '@/lib/tickets';
import { cn } from '@/lib/utils';
import { destroy, reveal } from '@/routes/agent/secrets';
import type { TicketSecret } from '@/types';

/** A revealed secret is hidden again after this long. */
const REVEAL_MS = 60_000;

/**
 * A secret sent with a reply. Signed-in customers answer or view it in a dialog (guests
 * following a magic link get the link, which asks them to sign in); agents see its status
 * and can reveal the customer's answer to a request.
 */
export function SecretCard({
    secret,
    requestedBy = null,
}: {
    secret: TicketSecret;
    /** Who sent the reply the secret came with. */
    requestedBy?: string | null;
}) {
    const { t, tChoice } = useTranslation();
    const { auth } = usePage().props;
    const [, copy] = useClipboard();
    const [revealed, setRevealed] = useState<string | null>(null);
    const [processing, setProcessing] = useState(false);
    const isStaff = auth.isStaff;
    const isSignedInCustomer = !isStaff && Boolean(auth.user);
    // What's left for the customer: answering a request, or viewing a share.
    const awaitsCustomer =
        secret.kind === 'request'
            ? secret.status === 'pending'
            : secret.status === 'available';
    const [dialogOpen, setDialogOpen] = useState(false);
    const isOpen = secret.status === 'pending' || secret.status === 'available';

    useEffect(() => {
        if (revealed === null) {
            return;
        }

        const timer = window.setTimeout(() => setRevealed(null), REVEAL_MS);

        return () => window.clearTimeout(timer);
    }, [revealed]);

    const revealSecret = async () => {
        setProcessing(true);
        const result = await postJson<{ secret: string; views_left: number }>(
            reveal.url(secret.token),
            {},
        );
        setProcessing(false);

        if (!result.ok) {
            toast.error(result.message || t('Something went wrong.'));

            return;
        }

        setRevealed(result.data.secret);
        router.reload({ only: ['messages', 'activity'] });
    };

    return (
        <div className="mt-3 rounded-lg border bg-background p-3 text-sm">
            <div className="flex flex-wrap items-center gap-x-2 gap-y-1">
                <KeyRound className="size-4 text-primary" />
                <span className="font-medium">{secret.label}</span>
                <span className="text-xs text-muted-foreground">
                    {secret.kind === 'request'
                        ? t('Secret requested')
                        : t('Secret shared')}
                </span>
                <span
                    className={cn(
                        'ml-auto rounded px-1.5 py-0.5 text-[11px] font-medium',
                        isOpen
                            ? 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300'
                            : 'bg-muted text-muted-foreground',
                    )}
                >
                    {secret.status_label}
                </span>
            </div>

            {isStaff && (
                <p className="mt-1 text-xs text-muted-foreground">
                    {t('Viewed :views of :max', {
                        views: secret.views,
                        max: secret.max_views,
                    })}
                    {isOpen &&
                        ` · ${t('expires :time', { time: relativeTime(secret.expires_at) })}`}
                </p>
            )}

            {revealed !== null && (
                <div className="mt-2 flex items-start gap-2 rounded-md bg-muted p-2">
                    <pre className="min-w-0 flex-1 font-mono text-xs break-all whitespace-pre-wrap">
                        {revealed}
                    </pre>
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        title={t('Copy')}
                        onClick={() => void copy(revealed)}
                    >
                        <Copy />
                    </Button>
                </div>
            )}

            <div className="mt-2 flex flex-wrap items-center gap-2">
                {isSignedInCustomer &&
                    (awaitsCustomer || dialogOpen) && (
                        // Stays open after the status changes, so the customer sees the result.
                        <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
                            <DialogTrigger asChild>
                                <Button type="button" size="sm">
                                    <ShieldCheck />{' '}
                                    {secret.kind === 'request'
                                        ? t('Provide securely')
                                        : t('View secret')}
                                </Button>
                            </DialogTrigger>
                            <DialogContent>
                                <DialogTitle className="flex items-center gap-2">
                                    <KeyRound className="size-4 text-primary" />
                                    {secret.label}
                                </DialogTitle>
                                <DialogDescription>
                                    {t(
                                        'Secrets are stored encrypted, never sent by email, and only shown to signed-in people on this request.',
                                    )}
                                </DialogDescription>
                                <SecretForm
                                    secret={{
                                        token: secret.token,
                                        kind: secret.kind,
                                        label: secret.label,
                                        status: secret.status,
                                        views_left: Math.max(
                                            0,
                                            secret.max_views - secret.views,
                                        ),
                                        expires_at: secret.expires_at,
                                        requested_by: requestedBy,
                                    }}
                                    onChange={() =>
                                        router.reload({ only: ['messages'] })
                                    }
                                />
                            </DialogContent>
                        </Dialog>
                    )}
                {!isStaff && !isSignedInCustomer && awaitsCustomer && (
                    <Button asChild size="sm">
                        <a href={secret.url}>
                            <ShieldCheck />{' '}
                            {secret.kind === 'request'
                                ? t('Provide securely')
                                : t('View secret')}
                        </a>
                    </Button>
                )}

                {isStaff && secret.can_reveal && (
                    <Button
                        type="button"
                        size="sm"
                        disabled={processing}
                        onClick={() => void revealSecret()}
                    >
                        <Eye /> {t('Reveal')}
                    </Button>
                )}
                {isStaff && isOpen && (
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        onClick={() =>
                            void copy(secret.url).then(() =>
                                toast.success(t('Link copied.')),
                            )
                        }
                    >
                        <Link2 /> {t('Copy link')}
                    </Button>
                )}
                {isStaff && secret.can_revoke && (
                    <ConfirmAction
                        trigger={
                            <Button type="button" variant="ghost" size="sm">
                                <Trash2 /> {t('Revoke')}
                            </Button>
                        }
                        title={t('Revoke “:label”?', { label: secret.label })}
                        description={t(
                            'Its encrypted content is deleted right away and the link stops working.',
                        )}
                        confirmLabel={t('Revoke')}
                        href={destroy.url(secret.token)}
                    />
                )}
                {isStaff && revealed !== null && (
                    <span className="text-xs text-muted-foreground">
                        {tChoice(
                            'Hidden again in :count second.|Hidden again in :count seconds.',
                            REVEAL_MS / 1000,
                        )}
                    </span>
                )}
            </div>
        </div>
    );
}
