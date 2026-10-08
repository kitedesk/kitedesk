import { Head, router, usePage } from '@inertiajs/react';
import { Check, Copy, KeyRound, RotateCw, Send, Trash2 } from 'lucide-react';
import { Fragment, useState } from 'react';
import { ConfirmAction } from '@/components/admin/confirm-action';
import { AdminPageHeader } from '@/components/admin/page-header';
import { Button } from '@/components/ui/button';
import { WebhookForm } from '@/components/webhooks/webhook-form';
import type { WebhookData } from '@/components/webhooks/webhook-form';
import { useClipboard } from '@/hooks/use-clipboard';
import { useTranslation } from '@/hooks/use-translation';
import { relativeTime } from '@/lib/tickets';
import { cn } from '@/lib/utils';
import { destroy, index, secret, test } from '@/routes/admin/webhooks';
import { redeliver } from '@/routes/admin/webhooks/deliveries';
import type { Option } from '@/types';

type Delivery = {
    id: number;
    uuid: string;
    event: string;
    payload: unknown;
    response_status: number | null;
    response_body: string | null;
    attempts: number;
    delivered_at: string | null;
    failed_at: string | null;
    created_at: string;
};

type Props = {
    webhook: WebhookData;
    deliveries: Delivery[];
    events: Option[];
};

export default function WebhookShow({ webhook, deliveries, events }: Props) {
    const { t, tChoice } = useTranslation();
    const { flash } = usePage();
    const newSecret = flash.webhookSecret;
    const [expanded, setExpanded] = useState<number | null>(null);

    return (
        <>
            <Head title={webhook.name} />
            <AdminPageHeader
                title={webhook.name}
                description={webhook.url}
                actions={
                    <>
                        <Button
                            variant="outline"
                            onClick={() =>
                                router.post(
                                    test.url(webhook.id),
                                    {},
                                    { preserveScroll: true },
                                )
                            }
                        >
                            <Send /> {t('Send test')}
                        </Button>
                        <ConfirmAction
                            trigger={
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    title={t('Delete')}
                                >
                                    <Trash2 />
                                </Button>
                            }
                            title={t('Delete this webhook?')}
                            description={t(
                                'Deliveries stop immediately and the delivery log is removed.',
                            )}
                            href={destroy.url(webhook.id)}
                        />
                    </>
                }
            />

            {newSecret && <SecretBanner secret={newSecret} />}

            <div className="grid gap-6 xl:grid-cols-[22rem_minmax(0,1fr)]">
                <section className="space-y-4 rounded-xl border bg-card p-4 shadow-xs">
                    <WebhookForm webhook={webhook} events={events} />
                    <div className="border-t pt-4">
                        <p className="mb-2 text-sm font-medium">
                            {t('Verifying requests')}
                        </p>
                        <p className="text-xs text-muted-foreground">
                            {t('Compute')}{' '}
                            <code>
                                HMAC-SHA256(secret, timestamp + "." + rawBody)
                            </code>{' '}
                            {t('and compare it with the header')}{' '}
                            <code>X-Support-Signature</code> (
                            <code>sha256=…</code>).{' '}
                            {t('Reject timestamps older than 5 minutes.')}
                        </p>
                        <ConfirmAction
                            trigger={
                                <Button
                                    variant="outline"
                                    size="sm"
                                    className="mt-3"
                                >
                                    <KeyRound /> {t('Rotate secret')}
                                </Button>
                            }
                            title={t('Rotate the signing secret?')}
                            description={t(
                                "The old secret stops working immediately. You'll see the new one once.",
                            )}
                            confirmLabel={t('Rotate')}
                            method="post"
                            href={secret.url(webhook.id)}
                        />
                    </div>
                </section>

                <section className="min-w-0">
                    <h2 className="mb-3 text-sm font-medium">
                        {t('Recent deliveries')}
                    </h2>
                    <div className="overflow-hidden rounded-xl border bg-card shadow-xs">
                        <table className="w-full text-sm">
                            <tbody>
                                {deliveries.map((delivery) => (
                                    <Fragment key={delivery.id}>
                                        <tr
                                            className="cursor-pointer border-b hover:bg-muted/50"
                                            onClick={() =>
                                                setExpanded(
                                                    expanded === delivery.id
                                                        ? null
                                                        : delivery.id,
                                                )
                                            }
                                        >
                                            <td className="px-4 py-2.5">
                                                <DeliveryStatus
                                                    delivery={delivery}
                                                />
                                            </td>
                                            <td className="px-3 py-2.5 font-mono text-xs">
                                                {delivery.event}
                                            </td>
                                            <td className="hidden px-3 py-2.5 text-xs text-muted-foreground md:table-cell">
                                                {tChoice(
                                                    ':count attempt|:count attempts',
                                                    delivery.attempts,
                                                )}
                                            </td>
                                            <td className="px-3 py-2.5 text-right text-xs text-muted-foreground">
                                                {relativeTime(
                                                    delivery.created_at,
                                                )}
                                            </td>
                                            <td className="w-10 px-2">
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    title={t('Redeliver')}
                                                    onClick={(event) => {
                                                        event.stopPropagation();
                                                        router.post(
                                                            redeliver.url({
                                                                webhook:
                                                                    webhook.id,
                                                                delivery:
                                                                    delivery.id,
                                                            }),
                                                            {},
                                                            {
                                                                preserveScroll: true,
                                                            },
                                                        );
                                                    }}
                                                >
                                                    <RotateCw />
                                                </Button>
                                            </td>
                                        </tr>
                                        {expanded === delivery.id && (
                                            <tr className="border-b bg-muted/30">
                                                <td
                                                    colSpan={5}
                                                    className="space-y-2 px-4 py-3"
                                                >
                                                    <p className="text-xs text-muted-foreground">
                                                        {t('Delivery :id', {
                                                            id: delivery.uuid,
                                                        })}
                                                    </p>
                                                    <pre className="max-h-64 overflow-auto rounded-md bg-background p-3 text-xs">
                                                        {JSON.stringify(
                                                            delivery.payload,
                                                            null,
                                                            2,
                                                        )}
                                                    </pre>
                                                    {delivery.response_body && (
                                                        <pre className="max-h-40 overflow-auto rounded-md bg-background p-3 text-xs">
                                                            {
                                                                delivery.response_body
                                                            }
                                                        </pre>
                                                    )}
                                                </td>
                                            </tr>
                                        )}
                                    </Fragment>
                                ))}
                                {deliveries.length === 0 && (
                                    <tr>
                                        <td className="px-4 py-12 text-center text-muted-foreground">
                                            {t(
                                                'No deliveries yet — send a test to try it out.',
                                            )}
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        </>
    );
}

function DeliveryStatus({ delivery }: { delivery: Delivery }) {
    const { t } = useTranslation();
    const state = delivery.delivered_at
        ? 'delivered'
        : delivery.failed_at
          ? 'failed'
          : 'pending';

    return (
        <span
            className={cn(
                'inline-flex items-center gap-1.5 rounded-md px-1.5 py-0.5 text-xs font-medium',
                state === 'delivered' &&
                    'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300',
                state === 'failed' &&
                    'bg-red-500/15 text-red-700 dark:text-red-300',
                state === 'pending' && 'bg-muted text-muted-foreground',
            )}
        >
            {delivery.response_status ?? '—'}{' '}
            {state === 'delivered'
                ? t('delivered')
                : state === 'failed'
                  ? t('failed')
                  : t('pending')}
        </span>
    );
}

function SecretBanner({ secret: value }: { secret: string }) {
    const { t } = useTranslation();
    const [copiedText, copy] = useClipboard();

    return (
        <div className="mb-6 rounded-xl border border-amber-300 bg-amber-50 p-4 dark:border-amber-500/40 dark:bg-amber-500/10">
            <p className="text-sm font-medium">
                {t("Signing secret — copy it now, it won't be shown again.")}
            </p>
            <div className="mt-2 flex items-center gap-2">
                <code className="flex-1 truncate rounded-md bg-background px-3 py-2 font-mono text-xs">
                    {value}
                </code>
                <Button
                    variant="outline"
                    size="icon"
                    onClick={() => void copy(value)}
                    title={t('Copy')}
                >
                    {copiedText === value ? <Check /> : <Copy />}
                </Button>
            </div>
        </div>
    );
}

WebhookShow.layout = {
    breadcrumbs: [
        { title: 'Admin center', href: '/admin' },
        { title: 'Webhooks', href: index() },
    ],
};
