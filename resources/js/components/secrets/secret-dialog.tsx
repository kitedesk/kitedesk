import { KeyRound, Link2, Paperclip } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import InputError from '@/components/input-error';
import { AnimatedTabs } from '@/components/ui/animated-tabs';
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
import { useClipboard } from '@/hooks/use-clipboard';
import { useTranslation } from '@/hooks/use-translation';
import { postJson } from '@/lib/post-json';
import { store as storeSecret } from '@/routes/agent/tickets/secrets';
import type { TicketSecret } from '@/types';

type Kind = TicketSecret['kind'];

/**
 * A secret waiting to go out with the reply.
 */
export type AttachedSecret = {
    token: string;
    kind: Kind;
    label: string;
};

const EXPIRY_HOURS = [1, 24, 72, 168, 336, 720];

/** Agents may need to look at a requested secret more than once; shares default to one view. */
const DEFAULT_VIEWS: Record<Kind, string> = { request: '3', share: '1' };

/**
 * Request a secret from the customer, or share one with them. Either way the customer has
 * to sign in to answer or read it.
 */
export function SecretDialog({
    ticketId,
    onAttach,
}: {
    ticketId: number;
    onAttach: (secret: AttachedSecret) => void;
}) {
    const { t, tChoice } = useTranslation();
    const [, copy] = useClipboard();
    const [open, setOpen] = useState(false);
    const [kind, setKind] = useState<Kind>('request');
    const [label, setLabel] = useState('');
    const [content, setContent] = useState('');
    const [maxViews, setMaxViews] = useState(DEFAULT_VIEWS.request);
    const [expiresIn, setExpiresIn] = useState('168');
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [processing, setProcessing] = useState(false);

    const reset = () => {
        setLabel('');
        setContent('');
        setMaxViews(DEFAULT_VIEWS[kind]);
        setExpiresIn('168');
        setErrors({});
    };

    const expiryLabel = (hours: number) =>
        hours < 24
            ? tChoice(':count hour|:count hours', hours)
            : tChoice(':count day|:count days', hours / 24);

    const create = async (then: 'attach' | 'copy') => {
        if (kind === 'share' && content.trim() === '') {
            setErrors({ secret: t('Write the secret to share.') });

            return;
        }

        setProcessing(true);
        setErrors({});

        const result = await postJson<{ secret: TicketSecret; url: string }>(
            storeSecret.url(ticketId),
            {
                kind,
                label,
                max_views: Number(maxViews),
                expires_in_hours: Number(expiresIn),
                ...(kind === 'share' ? { secret: content } : {}),
            },
        );

        setProcessing(false);

        if (!result.ok) {
            setErrors(
                Object.keys(result.errors).length > 0
                    ? result.errors
                    : { label: result.message || t('Something went wrong.') },
            );

            return;
        }

        if (then === 'copy') {
            await copy(result.data.url);
            toast.success(t('Link copied.'));
        } else {
            onAttach({
                token: result.data.secret.token,
                kind,
                label: result.data.secret.label,
            });
        }

        reset();
        setOpen(false);
    };

    return (
        <Dialog
            open={open}
            onOpenChange={(value) => {
                setOpen(value);

                if (!value) {
                    reset();
                }
            }}
        >
            <DialogTrigger asChild>
                <Button type="button" variant="ghost" size="sm">
                    <KeyRound /> {t('Secret')}
                </Button>
            </DialogTrigger>
            <DialogContent className="sm:max-w-lg">
                <DialogTitle>{t('Secret')}</DialogTitle>
                <DialogDescription>
                    {t(
                        'Secrets are stored encrypted and never sent by email. The customer signs in to their account to answer or view it.',
                    )}
                </DialogDescription>

                <AnimatedTabs
                    tabs={[
                        { id: 'request', label: t('Request from customer') },
                        { id: 'share', label: t('Share with customer') },
                    ]}
                    activeTab={kind}
                    onChange={(id) => {
                        setKind(id as Kind);
                        setMaxViews(DEFAULT_VIEWS[id as Kind]);
                        setErrors({});
                    }}
                    renderContent={false}
                    layoutId="secret-kind"
                    variant="pill"
                />

                <form
                    className="space-y-4"
                    onSubmit={(event) => {
                        event.preventDefault();
                        void create('attach');
                    }}
                >
                    <div className="grid gap-2">
                        <Label htmlFor="secret-label">
                            {kind === 'request'
                                ? t('What do you need?')
                                : t('What is it?')}
                        </Label>
                        <Input
                            id="secret-label"
                            value={label}
                            maxLength={100}
                            placeholder={t('e.g. VPN password')}
                            onChange={(event) => setLabel(event.target.value)}
                        />
                        <InputError message={errors.label ?? errors.kind} />
                    </div>

                    {kind === 'share' && (
                        <div className="grid gap-2">
                            <Label htmlFor="secret-content">
                                {t('Secret')}
                            </Label>
                            <textarea
                                id="secret-content"
                                value={content}
                                rows={3}
                                maxLength={10000}
                                autoComplete="off"
                                spellCheck={false}
                                onChange={(event) =>
                                    setContent(event.target.value)
                                }
                                className="rounded-md border bg-transparent px-3 py-2 font-mono text-sm shadow-xs outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30"
                            />
                            <InputError message={errors.secret} />
                        </div>
                    )}

                    <div className="grid grid-cols-2 gap-3">
                        <div className="grid gap-2">
                            <Label>
                                {kind === 'request'
                                    ? t('Agents can view it')
                                    : t('Can be viewed')}
                            </Label>
                            <Select
                                value={maxViews}
                                onValueChange={setMaxViews}
                            >
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {Array.from(
                                        { length: 10 },
                                        (_, index) => index + 1,
                                    ).map((count) => (
                                        <SelectItem
                                            key={count}
                                            value={String(count)}
                                        >
                                            {tChoice(
                                                ':count time|:count times',
                                                count,
                                            )}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={errors.max_views} />
                        </div>
                        <div className="grid gap-2">
                            <Label>{t('Expires after')}</Label>
                            <Select
                                value={expiresIn}
                                onValueChange={setExpiresIn}
                            >
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {EXPIRY_HOURS.map((hours) => (
                                        <SelectItem
                                            key={hours}
                                            value={String(hours)}
                                        >
                                            {expiryLabel(hours)}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={errors.expires_in_hours} />
                        </div>
                    </div>

                    <DialogFooter className="gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            disabled={processing}
                            onClick={() => void create('copy')}
                        >
                            <Link2 /> {t('Copy link')}
                        </Button>
                        <Button type="submit" disabled={processing}>
                            <Paperclip /> {t('Add to reply')}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
