import { Copy, Eye, ShieldCheck } from 'lucide-react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { useClipboard } from '@/hooks/use-clipboard';
import { useTranslation } from '@/hooks/use-translation';
import { postJson } from '@/lib/post-json';
import { formatDateTime } from '@/lib/tickets';
import { reveal, submit } from '@/routes/secrets';
import type { SecretStatus } from '@/types';

/**
 * A secret as the customer it was sent to sees it.
 */
export type CustomerSecret = {
    token: string;
    kind: 'request' | 'share';
    label: string;
    status: SecretStatus;
    views_left: number;
    expires_at: string;
    requested_by: string | null;
};

/**
 * Answer a request or reveal a shared secret, for the signed-in requester or someone
 * copied. Nothing is revealed or used up until they ask for it. `onChange` runs after a
 * secret was sent or viewed, so the page can refresh its status.
 */
export function SecretForm({
    secret,
    maxLength = 10000,
    onChange,
}: {
    secret: CustomerSecret;
    maxLength?: number;
    onChange?: () => void;
}) {
    return secret.kind === 'request' ? (
        <RequestForm
            secret={secret}
            maxLength={maxLength}
            onChange={onChange}
        />
    ) : (
        <SharedSecret secret={secret} onChange={onChange} />
    );
}

function Unavailable({ status }: { status: SecretStatus }) {
    const { t } = useTranslation();
    const messages: Partial<Record<SecretStatus, string>> = {
        used_up: t('This secret was already viewed as many times as allowed.'),
        expired: t('This link has expired.'),
        revoked: t('This link was revoked.'),
    };

    return (
        <p className="text-sm text-muted-foreground">
            {messages[status] ?? t('This link no longer works.')}{' '}
            {t('Ask us for a new one if you still need it.')}
        </p>
    );
}

function RequestForm({
    secret,
    maxLength,
    onChange,
}: {
    secret: CustomerSecret;
    maxLength: number;
    onChange?: () => void;
}) {
    const { t } = useTranslation();
    const [value, setValue] = useState('');
    const [error, setError] = useState<string | null>(null);
    const [processing, setProcessing] = useState(false);
    const [status, setStatus] = useState(secret.status);

    if (status === 'available' || status === 'used_up') {
        return (
            <p className="flex items-start gap-2 text-sm">
                <ShieldCheck className="mt-0.5 size-4 shrink-0 text-emerald-600" />
                {t('Thanks! Your secret was sent securely.')}
            </p>
        );
    }

    if (status !== 'pending') {
        return <Unavailable status={status} />;
    }

    const send = async () => {
        if (value.trim() === '') {
            setError(t('Enter the secret.'));

            return;
        }

        setProcessing(true);
        setError(null);

        const result = await postJson<{ status: SecretStatus }>(
            submit.url(secret.token),
            { secret: value },
        );

        setProcessing(false);

        if (!result.ok) {
            setError(
                result.errors.secret ??
                    (result.message || t('Something went wrong.')),
            );

            return;
        }

        setValue('');
        setStatus(result.data.status);
        onChange?.();
    };

    return (
        <form
            className="space-y-4"
            onSubmit={(event) => {
                event.preventDefault();
                void send();
            }}
        >
            <p className="text-sm text-muted-foreground">
                {secret.requested_by
                    ? t(':name asked you for this.', {
                          name: secret.requested_by,
                      })
                    : t('Our team asked you for this.')}{' '}
                {t('It is only shown to our team in your request.')}
            </p>
            <div className="grid gap-2">
                <Label htmlFor="secret-value">{t('Secret')}</Label>
                <textarea
                    id="secret-value"
                    value={value}
                    rows={4}
                    maxLength={maxLength}
                    autoComplete="off"
                    spellCheck={false}
                    autoFocus
                    onChange={(event) => setValue(event.target.value)}
                    className="rounded-md border bg-transparent px-3 py-2 font-mono text-sm shadow-xs outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30"
                />
                <InputError message={error ?? undefined} />
            </div>
            <p className="text-xs text-muted-foreground">
                {t('This link works until :date.', {
                    date: formatDateTime(secret.expires_at),
                })}
            </p>
            <Button type="submit" disabled={processing}>
                <ShieldCheck /> {t('Send securely')}
            </Button>
        </form>
    );
}

function SharedSecret({
    secret,
    onChange,
}: {
    secret: CustomerSecret;
    onChange?: () => void;
}) {
    const { t, tChoice } = useTranslation();
    const [, copy] = useClipboard();
    const [error, setError] = useState<string | null>(null);
    const [processing, setProcessing] = useState(false);
    const [revealed, setRevealed] = useState<{
        value: string;
        viewsLeft: number;
    } | null>(null);
    const [gone, setGone] = useState(false);

    if (revealed !== null) {
        return (
            <div className="space-y-3">
                <div className="flex items-start gap-2 rounded-md bg-muted p-3">
                    <pre className="min-w-0 flex-1 font-mono text-sm break-all whitespace-pre-wrap">
                        {revealed.value}
                    </pre>
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        title={t('Copy')}
                        onClick={() => void copy(revealed.value)}
                    >
                        <Copy />
                    </Button>
                </div>
                <p className="text-xs text-muted-foreground">
                    {revealed.viewsLeft > 0
                        ? tChoice(
                              'It can be viewed :count more time.|It can be viewed :count more times.',
                              revealed.viewsLeft,
                          )
                        : t(
                              'This was the last view: the secret is now deleted. Save it somewhere safe before leaving this page.',
                          )}
                </p>
            </div>
        );
    }

    if (gone || secret.status !== 'available') {
        return <Unavailable status={gone ? 'used_up' : secret.status} />;
    }

    const revealSecret = async () => {
        setProcessing(true);
        setError(null);

        const result = await postJson<{ secret: string; views_left: number }>(
            reveal.url(secret.token),
            {},
        );

        setProcessing(false);

        if (!result.ok) {
            if (result.status === 410) {
                setGone(true);
            } else {
                setError(result.message || t('Something went wrong.'));
            }

            return;
        }

        setRevealed({
            value: result.data.secret,
            viewsLeft: result.data.views_left,
        });
        onChange?.();
    };

    return (
        <div className="space-y-4">
            <p className="text-sm text-muted-foreground">
                {tChoice(
                    'We shared a secret with you. It can be viewed :count time, until :date.|We shared a secret with you. It can be viewed :count times, until :date.',
                    secret.views_left,
                    { date: formatDateTime(secret.expires_at) },
                )}
            </p>
            <InputError message={error ?? undefined} />
            <Button
                type="button"
                disabled={processing}
                onClick={() => void revealSecret()}
            >
                <Eye /> {t('Reveal secret')}
            </Button>
        </div>
    );
}
