import { Head } from '@inertiajs/react';
import { PlugZap } from 'lucide-react';
import { ConfirmAction } from '@/components/admin/confirm-action';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { useTranslation } from '@/hooks/use-translation';
import { relativeTime } from '@/lib/tickets';
import { destroy, index } from '@/routes/connected-apps';

type ConnectedApp = {
    id: string;
    name: string;
    connected_at: string | null;
    last_active_at: string | null;
};

export default function ConnectedApps({ apps }: { apps: ConnectedApp[] }) {
    const { t } = useTranslation();

    return (
        <>
            <Head title={t('Connected apps')} />

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title={t('Connected apps')}
                    description={t(
                        'AI apps you allowed to use KiteDesk as you, through the MCP server. They can do what your role allows.',
                    )}
                />

                {apps.length === 0 ? (
                    <div className="flex flex-col items-center gap-2 rounded-xl border border-dashed p-8 text-center text-sm text-muted-foreground">
                        <PlugZap className="size-5" />
                        {t('You have not connected any apps.')}
                    </div>
                ) : (
                    <ul className="divide-y rounded-xl border">
                        {apps.map((app) => (
                            <li
                                key={app.id}
                                className="flex items-center gap-4 p-4"
                            >
                                <div className="min-w-0 flex-1">
                                    <p className="truncate font-medium">
                                        {app.name}
                                    </p>
                                    <p className="text-xs text-muted-foreground">
                                        {app.connected_at &&
                                            t('Connected :time', {
                                                time: relativeTime(
                                                    app.connected_at,
                                                ),
                                            })}
                                        {app.last_active_at &&
                                            app.last_active_at !==
                                                app.connected_at &&
                                            ` · ${t('Signed in again :time', {
                                                time: relativeTime(
                                                    app.last_active_at,
                                                ),
                                            })}`}
                                    </p>
                                </div>
                                <ConfirmAction
                                    trigger={
                                        <Button variant="outline" size="sm">
                                            {t('Disconnect')}
                                        </Button>
                                    }
                                    title={t('Disconnect :name?', {
                                        name: app.name,
                                    })}
                                    description={t(
                                        'The app loses access right away. To use it again, connect it and approve it again.',
                                    )}
                                    confirmLabel={t('Disconnect')}
                                    href={destroy.url(app.id)}
                                />
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        </>
    );
}

ConnectedApps.layout = {
    breadcrumbs: [{ title: 'Connected apps', href: index() }],
};
