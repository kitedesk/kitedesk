import { Head, Link } from '@inertiajs/react';
import { useEcho } from '@laravel/echo-react';
import { useChannelName } from '@/hooks/use-channel-name';
import { ArrowLeft } from 'lucide-react';
import { RequestConversation } from '@/components/tickets/request-conversation';
import { SatisfactionCard } from '@/components/tickets/satisfaction-form';
import type { SatisfactionAnswer } from '@/components/tickets/satisfaction-form';
import { useTranslation } from '@/hooks/use-translation';
import { scheduleReload } from '@/lib/live-reload';
import { index } from '@/routes/portal/tickets';
import { store as storeReply } from '@/routes/portal/tickets/replies';
import { store as storeRating } from '@/routes/portal/tickets/satisfaction';
import type { Ticket, TicketMessage } from '@/types';

type Props = {
    ticket: Ticket;
    messages: TicketMessage[];
    earlierMessages: number;
    canReply: boolean;
    satisfaction: SatisfactionAnswer | null;
};

export default function RequestShow({
    ticket,
    messages,
    earlierMessages,
    canReply,
    satisfaction,
}: Props) {
    const { t } = useTranslation();
    const channelName = useChannelName();

    useEcho(
        channelName(`tickets.${ticket.id}`),
        ['.message.created', '.ticket.updated'],
        () =>
            scheduleReload([
                'ticket',
                'messages',
                'earlierMessages',
                'canReply',
                'satisfaction',
            ]),
        [ticket.id],
    );

    return (
        <>
            <Head title={ticket.subject} />

            <div className="mx-auto w-full max-w-3xl space-y-6 px-4 py-10">
                <Link
                    href={index()}
                    className="inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground"
                >
                    <ArrowLeft className="size-4" /> {t('My requests')}
                </Link>

                <RequestConversation
                    ticket={ticket}
                    messages={messages}
                    earlierMessages={earlierMessages}
                    canReply={canReply}
                    replyUrl={storeReply.url(ticket.id)}
                />

                {satisfaction && (
                    <SatisfactionCard
                        url={storeRating.url(ticket.id)}
                        answer={satisfaction}
                    />
                )}
            </div>
        </>
    );
}
