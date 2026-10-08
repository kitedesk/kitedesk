import { Head, Link, router, usePage } from '@inertiajs/react';
import { useEcho } from '@laravel/echo-react';
import { useChannelName } from '@/hooks/use-channel-name';
import { MessageSquare, PlusCircle } from 'lucide-react';
import { StatusBadge } from '@/components/tickets/status-badge';
import { AnimatedTabs } from '@/components/ui/animated-tabs';
import { Button } from '@/components/ui/button';
import { SlidePagination } from '@/components/ui/slide-pagination';
import { useTranslation } from '@/hooks/use-translation';
import { scheduleReload } from '@/lib/live-reload';
import { relativeTime } from '@/lib/tickets';
import { create, index, show } from '@/routes/portal/tickets';
import type { Paginated, Ticket } from '@/types';

type Props = {
    status: 'open' | 'solved' | 'shared';
    tickets: Paginated<Ticket>;
};

export default function MyRequests({ status, tickets }: Props) {
    const { t, tChoice } = useTranslation();
    const { auth } = usePage().props;
    const channelName = useChannelName();

    // Replies and status changes on any of the customer's requests, including CCs.
    useEcho(
        channelName(`App.Models.User.${auth.user.id}`),
        ['.ticket.created', '.ticket.updated', '.message.created'],
        () => scheduleReload(['tickets']),
        [auth.user.id],
    );

    return (
        <>
            <Head title={t('My requests')} />

            <div className="mx-auto w-full max-w-4xl space-y-6 px-4 py-10">
                <div className="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            {t('My requests')}
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {t(
                                "Track the conversations you've had with our support team.",
                            )}
                        </p>
                    </div>
                    <Button asChild>
                        <Link href={create()}>
                            <PlusCircle /> {t('New request')}
                        </Link>
                    </Button>
                </div>

                <AnimatedTabs
                    tabs={[
                        { id: 'open', label: t('Open') },
                        { id: 'solved', label: t('Solved') },
                        { id: 'shared', label: t('Shared with me') },
                    ]}
                    activeTab={status}
                    renderContent={false}
                    layoutId="portal-status"
                    variant="pill"
                    onChange={(id) =>
                        router.get(
                            index.url(),
                            id === 'open' ? {} : { status: id },
                            {
                                preserveState: true,
                                replace: true,
                            },
                        )
                    }
                />

                {tickets.data.length === 0 ? (
                    <div className="rounded-xl border border-dashed px-6 py-16 text-center">
                        <MessageSquare className="mx-auto mb-3 size-8 text-muted-foreground" />
                        <p className="font-medium">
                            {status === 'open'
                                ? t('No open requests')
                                : status === 'solved'
                                  ? t('No solved requests yet')
                                  : t('Nobody has copied you on a request yet')}
                        </p>
                        <p className="mt-1 text-sm text-muted-foreground">
                            {t(
                                'Need help? Search the help center or send us a request.',
                            )}
                        </p>
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
                                        <p className="mt-0.5 text-xs text-muted-foreground">
                                            {ticket.number} ·{' '}
                                            {t('updated :time', {
                                                time: relativeTime(
                                                    ticket.updated_at,
                                                ),
                                            })}
                                            {ticket.messages_count !==
                                                undefined &&
                                                ` · ${tChoice(':count message|:count messages', ticket.messages_count)}`}
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
