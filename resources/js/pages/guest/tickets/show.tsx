import { Head, usePoll } from '@inertiajs/react';
import { RequestConversation } from '@/components/tickets/request-conversation';
import { SatisfactionCard } from '@/components/tickets/satisfaction-form';
import type { SatisfactionAnswer } from '@/components/tickets/satisfaction-form';
import { useTranslation } from '@/hooks/use-translation';
import { store as storeReply } from '@/routes/guest/tickets/replies';
import { store as storeRating } from '@/routes/guest/tickets/satisfaction';
import type { Ticket, TicketMessage } from '@/types';

type Props = {
    ticket: Ticket;
    messages: TicketMessage[];
    earlierMessages: number;
    guest: { id: number; name: string };
    canReply: boolean;
    satisfaction: SatisfactionAnswer | null;
};

/**
 * A request opened from an emailed link, for people without an account.
 */
export default function GuestRequestShow({
    ticket,
    messages,
    earlierMessages,
    guest,
    canReply,
    satisfaction,
}: Props) {
    const { t } = useTranslation();

    // Guests have no account to sign a private channel with, so they check for news instead.
    usePoll(20000, {
        only: [
            'ticket',
            'messages',
            'earlierMessages',
            'canReply',
            'satisfaction',
        ],
    });

    return (
        <>
            <Head title={ticket.subject} />

            <div className="mx-auto w-full max-w-3xl space-y-6 px-4 py-10">
                <p className="text-sm text-muted-foreground">
                    {t(
                        'Viewing as :name. Keep the link from your email to come back later.',
                        {
                            name: guest.name,
                        },
                    )}
                </p>

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
