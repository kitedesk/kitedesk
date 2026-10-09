import { Head } from '@inertiajs/react';
import { useEcho } from '@laravel/echo-react';
import { RequestConversation } from '@/components/tickets/request-conversation';
import { useChannelName } from '@/hooks/use-channel-name';
import { useTranslation } from '@/hooks/use-translation';
import { scheduleReload } from '@/lib/live-reload';
import { index } from '@/routes/agent/requests';
import { store as storeReply } from '@/routes/agent/requests/replies';
import type { Ticket, TicketMessage } from '@/types';

type Props = {
    ticket: Ticket;
    messages: TicketMessage[];
    earlierMessages: number;
    canReply: boolean;
};

export default function InternalRequestShow({
    ticket,
    messages,
    earlierMessages,
    canReply,
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
            ]),
        [ticket.id],
    );

    return (
        <>
            <Head title={ticket.subject} />

            <div className="mx-auto w-full max-w-3xl space-y-6 p-4 md:p-8">
                {ticket.group && (
                    <p className="text-sm text-muted-foreground">
                        {t('Sent to :group', { group: ticket.group.name })}
                    </p>
                )}

                <RequestConversation
                    ticket={ticket}
                    messages={messages}
                    earlierMessages={earlierMessages}
                    canReply={canReply}
                    replyUrl={storeReply.url(ticket.id)}
                />
            </div>
        </>
    );
}

InternalRequestShow.layout = {
    breadcrumbs: [{ title: 'My requests', href: index() }],
};
