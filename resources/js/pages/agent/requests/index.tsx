import { Head, Link, router, usePage } from '@inertiajs/react';
import { useEcho } from '@laravel/echo-react';
import { PlusCircle, Send } from 'lucide-react';
import Heading from '@/components/heading';
import { StatusBadge } from '@/components/tickets/status-badge';
import { AnimatedTabs } from '@/components/ui/animated-tabs';
import { Button } from '@/components/ui/button';
import { SlidePagination } from '@/components/ui/slide-pagination';
import { useChannelName } from '@/hooks/use-channel-name';
import { useEntitlements } from '@/hooks/use-entitlements';
import { usePermissions } from '@/hooks/use-permissions';
import { useTranslation } from '@/hooks/use-translation';
import { scheduleReload } from '@/lib/live-reload';
import { relativeTime } from '@/lib/tickets';
import { create, index, show } from '@/routes/agent/requests';
import type { Paginated, Ticket } from '@/types';

type Props = {
    status: 'open' | 'solved';
    tickets: Paginated<Ticket>;
};

export default function InternalRequests({ status, tickets }: Props) {
    const { t } = useTranslation();
    const { auth } = usePage().props;
    const { can } = usePermissions();
    const { includes } = useEntitlements();
    const channelName = useChannelName();
    const canCreate = can('tickets.create') && includes('internal_requests');

    // Replies and status changes on the agent's requests, as for customers.
    useEcho(
        channelName(`App.Models.User.${auth.user.id}`),
        ['.ticket.created', '.ticket.updated', '.message.created'],
        () => scheduleReload(['tickets']),
        [auth.user.id],
    );

    return (
        <>
            <Head title={t('My requests')} />

            <div className="mx-auto w-full max-w-4xl space-y-6 p-4 md:p-8">
                <div className="flex flex-wrap items-end justify-between gap-4">
                    <Heading
                        title={t('My requests')}
                        description={t(
                            'What you asked other departments for, and their answers.',
                        )}
                    />
                    {canCreate && (
                        <Button asChild>
                            <Link href={create()}>
                                <PlusCircle /> {t('New internal request')}
                            </Link>
                        </Button>
                    )}
                </div>

                <AnimatedTabs
                    tabs={[
                        { id: 'open', label: t('Open') },
                        { id: 'solved', label: t('Solved') },
                    ]}
                    activeTab={status}
                    renderContent={false}
                    layoutId="internal-requests-status"
                    variant="pill"
                    onChange={(id) =>
                        router.get(
                            index.url(),
                            id === 'open' ? {} : { status: id },
                            { preserveState: true, replace: true },
                        )
                    }
                />

                {tickets.data.length === 0 ? (
                    <div className="rounded-xl border border-dashed px-6 py-16 text-center">
                        <Send className="mx-auto mb-3 size-8 text-muted-foreground" />
                        <p className="font-medium">
                            {status === 'open'
                                ? t('No open requests')
                                : t('No solved requests yet')}
                        </p>
                        {canCreate && (
                            <p className="mt-1 text-sm text-muted-foreground">
                                {t(
                                    'Need something from another department? Send them a request.',
                                )}
                            </p>
                        )}
                    </div>
                ) : (
                    <ul className="divide-y overflow-hidden rounded-xl border bg-card shadow-xs">
                        {tickets.data.map((ticket) => (
                            <li key={ticket.id}>
                                <Link
                                    href={show(ticket.id)}
                                    prefetch
                                    className="flex items-center gap-4 px-5 py-4 transition-colors hover:bg-muted/50"
                                >
                                    <div className="min-w-0 flex-1">
                                        <p className="truncate font-medium">
                                            {ticket.subject}
                                        </p>
                                        <p className="mt-0.5 truncate text-xs text-muted-foreground">
                                            {[
                                                ticket.number,
                                                ticket.group?.name,
                                                ticket.assignee?.name,
                                                t('updated :time', {
                                                    time: relativeTime(
                                                        ticket.updated_at,
                                                    ),
                                                }),
                                            ]
                                                .filter(Boolean)
                                                .join(' · ')}
                                        </p>
                                    </div>
                                    <StatusBadge
                                        status={
                                            ticket.status === 'new'
                                                ? 'open'
                                                : ticket.status
                                        }
                                    />
                                </Link>
                            </li>
                        ))}
                    </ul>
                )}

                {tickets.meta.last_page > 1 && (
                    <div className="flex justify-center">
                        <SlidePagination
                            pageCount={tickets.meta.last_page}
                            page={tickets.meta.current_page}
                            onChange={(page) =>
                                router.get(
                                    index.url(),
                                    {
                                        ...(status !== 'open'
                                            ? { status }
                                            : {}),
                                        page,
                                    },
                                    { preserveState: true },
                                )
                            }
                        />
                    </div>
                )}
            </div>
        </>
    );
}

InternalRequests.layout = {
    breadcrumbs: [{ title: 'My requests', href: index() }],
};
