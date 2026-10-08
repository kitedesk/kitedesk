import { Head, Link } from '@inertiajs/react';
import { AlertTriangle, Plus, Webhook } from 'lucide-react';
import { useState } from 'react';
import { AdminPageHeader } from '@/components/admin/page-header';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogTitle,
} from '@/components/ui/dialog';
import { WebhookForm } from '@/components/webhooks/webhook-form';
import { useTranslation } from '@/hooks/use-translation';
import type { WebhookData } from '@/components/webhooks/webhook-form';
import { index, show } from '@/routes/admin/webhooks';
import type { Option } from '@/types';

type Props = {
    webhooks: (WebhookData & { failed_deliveries_count: number })[];
    events: Option[];
};

export default function WebhooksIndex({ webhooks, events }: Props) {
    const { t, tChoice } = useTranslation();
    const [creating, setCreating] = useState(false);

    return (
        <>
            <Head title={t('Webhooks')} />
            <AdminPageHeader
                title={t('Webhooks')}
                description={t(
                    'POST signed JSON to your systems when tickets are created, updated, solved or receive messages.',
                )}
                actions={
                    <Button onClick={() => setCreating(true)}>
                        <Plus /> {t('New webhook')}
                    </Button>
                }
            />

            {webhooks.length === 0 ? (
                <div className="rounded-xl border border-dashed px-6 py-16 text-center text-sm text-muted-foreground">
                    <Webhook className="mx-auto mb-2 size-6" />
                    {t('No webhooks yet.')}
                </div>
            ) : (
                <ul className="divide-y overflow-hidden rounded-xl border bg-card shadow-xs">
                    {webhooks.map((webhook) => (
                        <li key={webhook.id}>
                            <Link
                                href={show(webhook.id)}
                                className="flex items-center gap-3 px-4 py-3 hover:bg-muted/50"
                            >
                                <span
                                    className={`size-2 shrink-0 rounded-full ${webhook.is_active ? 'bg-emerald-500' : 'bg-zinc-400'}`}
                                />
                                <div className="min-w-0 flex-1">
                                    <p className="font-medium">
                                        {webhook.name}
                                    </p>
                                    <p className="truncate font-mono text-xs text-muted-foreground">
                                        {webhook.url}
                                    </p>
                                </div>
                                {webhook.failed_deliveries_count > 0 && (
                                    <span className="inline-flex items-center gap-1 text-xs text-red-600 dark:text-red-400">
                                        <AlertTriangle className="size-3.5" />{' '}
                                        {t(':count failed (24h)', {
                                            count: webhook.failed_deliveries_count,
                                        })}
                                    </span>
                                )}
                                <span className="hidden text-xs text-muted-foreground sm:inline">
                                    {tChoice(
                                        ':count event|:count events',
                                        webhook.events.length,
                                    )}
                                </span>
                            </Link>
                        </li>
                    ))}
                </ul>
            )}

            <Dialog open={creating} onOpenChange={setCreating}>
                <DialogContent>
                    <DialogTitle>{t('New webhook')}</DialogTitle>
                    <DialogDescription>
                        {t(
                            'A signing secret is generated and shown once after creation.',
                        )}
                    </DialogDescription>
                    <WebhookForm webhook={null} events={events} />
                </DialogContent>
            </Dialog>
        </>
    );
}

WebhooksIndex.layout = {
    breadcrumbs: [
        { title: 'Admin center', href: '/admin' },
        { title: 'Webhooks', href: index() },
    ],
};
