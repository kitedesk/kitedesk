import { Head, Link, setLayoutProps, usePage } from '@inertiajs/react';
import { useEcho, usePresenceChannel } from '@laravel/echo-react';
import { useChannelName } from '@/hooks/use-channel-name';
import { Building2, Clock, History, Mail, Phone, Trash2 } from 'lucide-react';
import { useEffect, useLayoutEffect, useRef, useState } from 'react';
import { ConfirmAction } from '@/components/admin/confirm-action';
import { MessageThread } from '@/components/tickets/message-thread';
import { PriorityLabel } from '@/components/tickets/priority-label';
import type { AiFeatures } from '@/components/tickets/ai-assistant';
import { ReplyComposer } from '@/components/tickets/reply-composer';
import { SlaTimer } from '@/components/tickets/sla-timer';
import { StatusBadge } from '@/components/tickets/status-badge';
import { TicketProperties } from '@/components/tickets/ticket-properties';
import { UserAvatar } from '@/components/tickets/user-avatar';
import type { CannedResponseOption } from '@/components/tickets/canned-response-picker';
import { ForwardDialog } from '@/components/tickets/forward-dialog';
import { MergeDialog } from '@/components/tickets/merge-dialog';
import { StarRating } from '@/components/tickets/star-rating';
import {
    SummarizeButton,
    TicketSummary,
} from '@/components/tickets/ticket-summary';
import { WorkflowMenu } from '@/components/tickets/workflow-menu';
import type { TicketWorkflows } from '@/components/tickets/workflow-menu';
import { RelatedTickets } from '@/components/tickets/related-tickets';
import type { LinkedTicket } from '@/components/tickets/related-tickets';
import { AvatarStack } from '@/components/ui/avatar-stack';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { usePermissions } from '@/hooks/use-permissions';
import { useTranslation } from '@/hooks/use-translation';
import { scheduleReload } from '@/lib/live-reload';
import {
    displayValue,
    formatDateTime,
    relativeTime,
    statusLabel,
} from '@/lib/tickets';
import { destroy, index, show } from '@/routes/agent/tickets';
import { store as storeMessage } from '@/routes/agent/tickets/messages';
import type {
    Ticket,
    TicketField,
    TicketMessage,
    TicketOptions,
    TicketSatisfaction,
    TicketStatus,
} from '@/types';

type Activity = {
    id: number;
    event: string | null;
    causer: string | null;
    workflow: string | null;
    score: number | null;
    /** Label of the secret a `secret_*` event is about. */
    secret: string | null;
    changes: { old: Record<string, unknown>; new: Record<string, unknown> };
    created_at: string;
};

/**
 * Customers answer and view secrets; without a causer, their account has since been deleted.
 */
function isCustomerSecretEvent(event: string | null): boolean {
    return event === 'secret_submitted' || event === 'secret_viewed';
}

type Props = {
    ticket: Ticket;
    messages: TicketMessage[];
    earlierMessages: number;
    requester: {
        id: number;
        name: string;
        email: string;
        avatar: string | null;
        job_title: string | null;
        phone: string | null;
        timezone: string | null;
        organization: { id: number; name: string } | null;
        created_at: string;
    };
    requesterTickets?: Ticket[];
    activity?: Activity[];
    ticketFields: TicketField[];
    options: TicketOptions;
    can: {
        update: boolean;
        reply: boolean;
        addInternalNote: boolean;
        forward: boolean;
        merge: boolean;
        delete: boolean;
        useSecrets: boolean;
    };
    ai: AiFeatures;
    mergedInto: { id: number; number: string; subject: string } | null;
    linkedTickets: LinkedTicket[];
    workflows: TicketWorkflows;
    cannedResponses?: CannedResponseOption[];
    replyStatusId: number | null;
    signature: string | null;
    satisfaction: TicketSatisfaction | null;
};

type Viewer = { id: number; name: string };

type ReplyingWhisper = Viewer & { replying: boolean };

/**
 * How long a "replying" signal lasts without a refresh, e.g. when someone closes the tab
 * mid-draft and the leave event is slow to arrive.
 */
const REPLYING_TTL_MS = 8000;

/**
 * At most one "still replying" whisper this often while typing.
 */
const REPLYING_REFRESH_MS = 3000;

/**
 * Other staff members on this ticket right now, and which of them are writing a reply or
 * note (collision detection). Typing is shared as client whispers on the presence channel.
 */
function useTicketPresence(ticketId: number, currentUser: Viewer) {
    const channelName = useChannelName();
    const [viewers, setViewers] = useState<Viewer[]>([]);
    const [replying, setReplying] = useState<
        (Viewer & { expiresAt: number })[]
    >([]);
    const lastSent = useRef<{ replying: boolean; at: number }>({
        replying: false,
        at: 0,
    });
    const { channel } = usePresenceChannel(
        channelName(`staff.tickets.${ticketId}`),
    );

    useEffect(() => {
        const presence = channel();

        if (!presence) {
            return;
        }

        setViewers([]);
        setReplying([]);
        lastSent.current = { replying: false, at: 0 };

        const forget = (member: Viewer) =>
            setReplying((current) => current.filter((m) => m.id !== member.id));

        presence
            .here((members: Viewer[]) => setViewers(members))
            .joining((member: Viewer) =>
                setViewers((current) => [
                    ...current.filter((m) => m.id !== member.id),
                    member,
                ]),
            )
            .leaving((member: Viewer) => {
                setViewers((current) =>
                    current.filter((m) => m.id !== member.id),
                );
                forget(member);
            })
            .listenForWhisper('replying', (event: ReplyingWhisper) => {
                if (!event.replying) {
                    forget(event);

                    return;
                }

                setReplying((current) => [
                    ...current.filter((m) => m.id !== event.id),
                    {
                        id: event.id,
                        name: event.name,
                        expiresAt: Date.now() + REPLYING_TTL_MS,
                    },
                ]);
            });

        return () => {
            presence.stopListeningForWhisper('replying');
        };
    }, [channel]);

    // Drop signals nobody has refreshed.
    useEffect(() => {
        if (replying.length === 0) {
            return;
        }

        const timer = window.setInterval(() => {
            const now = Date.now();

            setReplying((current) =>
                current.some((m) => m.expiresAt <= now)
                    ? current.filter((m) => m.expiresAt > now)
                    : current,
            );
        }, 1000);

        return () => window.clearInterval(timer);
    }, [replying.length]);

    const signalReplying = (isReplying: boolean) => {
        const now = Date.now();
        const previous = lastSent.current;

        if (
            isReplying === previous.replying &&
            (!isReplying || now - previous.at < REPLYING_REFRESH_MS)
        ) {
            return;
        }

        lastSent.current = { replying: isReplying, at: now };
        channel()?.whisper('replying', {
            id: currentUser.id,
            name: currentUser.name,
            replying: isReplying,
        } satisfies ReplyingWhisper);
    };

    return {
        viewers: viewers.filter((viewer) => viewer.id !== currentUser.id),
        replying: replying.filter((viewer) => viewer.id !== currentUser.id),
        signalReplying,
    };
}

export default function TicketShow({
    ticket,
    messages,
    earlierMessages,
    requester,
    requesterTickets,
    activity,
    ticketFields,
    options,
    can: canOnTicket,
    ai,
    mergedInto,
    linkedTickets,
    workflows,
    cannedResponses,
    replyStatusId,
    signature,
    satisfaction,
}: Props) {
    const { auth } = usePage().props;
    const { can } = usePermissions();
    const channelName = useChannelName();
    const { t } = useTranslation();
    const { viewers, replying, signalReplying } = useTicketPresence(
        ticket.id,
        auth.user,
    );
    // Tracked per ticket, so moving to another ticket doesn't carry the summary over.
    const [summaryTicketId, setSummaryTicketId] = useState<number | null>(null);
    const showSummary = summaryTicketId === ticket.id;
    const setShowSummary = (open: boolean) =>
        setSummaryTicketId(open ? ticket.id : null);

    setLayoutProps({
        breadcrumbs: [
            { title: 'Tickets', href: index() },
            { title: ticket.number, href: show(ticket.id) },
        ],
    });

    // Open on the latest message, which sits just above the composer, and stay there as
    // messages arrive, unless the agent has scrolled up to read older ones.
    const conversation = useRef<HTMLDivElement>(null);
    const followLatest = useRef(true);

    useLayoutEffect(() => {
        followLatest.current = true;
    }, [ticket.id]);

    useLayoutEffect(() => {
        const element = conversation.current;

        if (element && followLatest.current) {
            element.scrollTop = element.scrollHeight;
        }
    }, [ticket.id, messages]);

    useEcho(
        channelName(`staff.tickets.${ticket.id}`),
        '.ticket.updated',
        () =>
            scheduleReload(['ticket', 'ticketFields', 'activity', 'workflows']),
        [ticket.id],
    );

    useEcho(
        channelName(`staff.tickets.${ticket.id}`),
        '.message.created',
        () =>
            scheduleReload([
                'ticket',
                'messages',
                'earlierMessages',
                'activity',
            ]),
        [ticket.id],
    );

    return (
        <>
            <Head title={`${ticket.number} ${ticket.subject}`} />

            {/* From lg up the page fits the screen: the sidebars and the conversation scroll on their own, while the ticket header and the composer stay put. */}
            <div className="grid flex-1 grid-cols-1 lg:h-[calc(100svh-5rem)] lg:flex-none lg:grid-cols-[17rem_minmax(0,1fr)] lg:grid-rows-[minmax(0,1fr)] lg:overflow-hidden lg:group-has-data-[collapsible=icon]/sidebar-wrapper:h-[calc(100svh-4rem)] xl:grid-cols-[17rem_minmax(0,1fr)_19rem]">
                <aside className="order-2 border-t p-4 lg:order-1 lg:overflow-y-auto lg:border-t-0 lg:border-r">
                    <TicketProperties
                        ticket={ticket}
                        options={options}
                        fields={ticketFields}
                        currentUserId={auth.user.id}
                        satisfaction={satisfaction}
                        disabled={!canOnTicket.update}
                    />
                    <div className="mt-6 border-t pt-4">
                        <RelatedTickets
                            ticketId={ticket.id}
                            tickets={linkedTickets}
                            disabled={!canOnTicket.merge}
                        />
                    </div>
                </aside>

                <section className="order-1 flex min-w-0 flex-col lg:order-2 lg:min-h-0">
                    <header className="shrink-0 border-b px-4 py-4 md:px-6">
                        <div className="flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
                            <StatusBadge
                                status={ticket.status}
                                customStatus={ticket.custom_status}
                            />
                            <span className="tabular-nums">
                                {ticket.number}
                            </span>
                            <span>·</span>
                            <span>
                                {t('via :channel', {
                                    channel: t(
                                        channelLabels[ticket.channel] ??
                                            ticket.channel,
                                    ),
                                })}
                            </span>
                            <span>·</span>
                            <span title={formatDateTime(ticket.created_at)}>
                                {t('opened :time', {
                                    time: relativeTime(ticket.created_at),
                                })}
                            </span>
                            {ticket.category && (
                                <>
                                    <span>·</span>
                                    <span>{ticket.category.path}</span>
                                </>
                            )}
                            {viewers.length > 0 && (
                                <div className="ml-auto flex items-center gap-2">
                                    <AvatarStack
                                        size="sm"
                                        avatars={viewers.map((viewer) => ({
                                            id: viewer.id,
                                            name: viewer.name,
                                            alt: viewer.name,
                                        }))}
                                        showTooltip
                                    />
                                    {replying.length > 0 ? (
                                        <span className="flex items-center gap-1.5 font-medium text-amber-600 dark:text-amber-400">
                                            <span className="size-1.5 animate-pulse rounded-full bg-current" />
                                            {replying.length === 1
                                                ? t(':name is replying…', {
                                                      name: replying[0].name,
                                                  })
                                                : t(
                                                      ':count agents are replying…',
                                                      {
                                                          count: replying.length,
                                                      },
                                                  )}
                                        </span>
                                    ) : (
                                        <span className="text-amber-600 dark:text-amber-400">
                                            {viewers.length === 1
                                                ? t(':name is also here', {
                                                      name: viewers[0].name,
                                                  })
                                                : t(
                                                      ':count agents are also here',
                                                      {
                                                          count: viewers.length,
                                                      },
                                                  )}
                                        </span>
                                    )}
                                </div>
                            )}
                        </div>
                        <div className="mt-2 flex items-start gap-3">
                            <h1 className="min-w-0 flex-1 text-xl font-semibold tracking-tight text-balance">
                                {ticket.subject}
                            </h1>
                            {ai.summaries && !showSummary && (
                                <SummarizeButton
                                    onClick={() => setShowSummary(true)}
                                />
                            )}
                            <WorkflowMenu
                                ticketId={ticket.id}
                                workflows={workflows}
                                canOpenWorkflows={can('admin.automation')}
                            />
                            {canOnTicket.forward && !mergedInto && (
                                <ForwardDialog
                                    ticketId={ticket.id}
                                    ticketNumber={ticket.number}
                                    agents={options.agents}
                                    groups={options.groups}
                                    currentUserId={auth.user.id}
                                    assigneeId={ticket.assignee?.id ?? null}
                                    reloadProps={[
                                        'ticket',
                                        'messages',
                                        'earlierMessages',
                                        'replyStatusId',
                                        'activity',
                                        'can',
                                        'agentNav',
                                    ]}
                                />
                            )}
                            {canOnTicket.merge && !mergedInto && (
                                <MergeDialog
                                    ticketId={ticket.id}
                                    ticketNumber={ticket.number}
                                    requesterId={requester.id}
                                />
                            )}
                            {canOnTicket.delete && (
                                <ConfirmAction
                                    trigger={
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            className="text-destructive hover:text-destructive"
                                            aria-label={t('Delete ticket')}
                                            title={t('Delete ticket')}
                                        >
                                            <Trash2 />
                                        </Button>
                                    }
                                    title={t('Delete :number?', {
                                        number: ticket.number,
                                    })}
                                    description={t(
                                        'The ticket, its messages and attachments are removed for good. This cannot be undone.',
                                    )}
                                    href={destroy.url(ticket.id)}
                                />
                            )}
                        </div>
                        {mergedInto && (
                            <p className="mt-3 rounded-lg border border-sky-300/60 bg-sky-50 px-3 py-2 text-sm text-sky-900 dark:border-sky-500/30 dark:bg-sky-500/10 dark:text-sky-200">
                                {t('This ticket was merged into')}{' '}
                                <Link
                                    href={show(mergedInto.id)}
                                    className="font-medium underline"
                                >
                                    {mergedInto.number} {mergedInto.subject}
                                </Link>
                            </p>
                        )}
                        <div className="mt-2 flex flex-wrap items-center gap-3 text-sm">
                            <PriorityLabel priority={ticket.priority} />
                            {ticket.sla && (
                                <SlaTimer sla={ticket.sla} showLabel />
                            )}
                            {ticket.sla?.policy && (
                                <span className="text-xs text-muted-foreground">
                                    {ticket.sla.policy}
                                </span>
                            )}
                        </div>
                    </header>

                    <div
                        ref={conversation}
                        onScroll={(event) => {
                            const element = event.currentTarget;

                            followLatest.current =
                                element.scrollHeight -
                                    element.scrollTop -
                                    element.clientHeight <
                                48;
                        }}
                        className="flex-1 px-4 py-6 md:px-6 lg:min-h-0 lg:overflow-y-auto lg:overscroll-contain"
                    >
                        {ai.summaries && showSummary && (
                            <TicketSummary
                                ticketId={ticket.id}
                                latestMessageId={messages.at(-1)?.id ?? null}
                                onClose={() => setShowSummary(false)}
                            />
                        )}
                        <MessageThread
                            messages={messages}
                            requesterId={requester.id}
                            earlierCount={earlierMessages}
                        />
                    </div>

                    <div className="shrink-0 px-4 pb-6 md:px-6 lg:max-h-[60%] lg:overflow-y-auto lg:border-t lg:pt-4 lg:pb-4">
                        {ticket.status === 'closed' ? (
                            <p className="rounded-lg border border-dashed p-4 text-center text-sm text-muted-foreground">
                                {t(
                                    'This ticket is closed. Ask the requester to open a follow-up request if needed.',
                                )}
                            </p>
                        ) : (
                            <div className="rounded-xl border bg-card p-4 shadow-xs">
                                <ReplyComposer
                                    action={storeMessage.url(ticket.id)}
                                    ticketId={ticket.id}
                                    reloadProps={[
                                        'ticket',
                                        'messages',
                                        'earlierMessages',
                                        'activity',
                                        'can',
                                        'agentNav',
                                    ]}
                                    currentStatus={ticket.status}
                                    currentCustomStatus={ticket.custom_status}
                                    customStatuses={options.customStatuses}
                                    preferredStatusId={replyStatusId}
                                    signature={signature}
                                    onDraftChange={signalReplying}
                                    ai={ai}
                                    canReply={canOnTicket.reply}
                                    canAddNote={canOnTicket.addInternalNote}
                                    canUseSecrets={canOnTicket.useSecrets}
                                    cannedResponses={cannedResponses}
                                    placeholderValues={{
                                        'requester.name': requester.name,
                                        'requester.first_name':
                                            requester.name.split(' ')[0],
                                        'requester.email': requester.email,
                                        'ticket.id': String(ticket.id),
                                        'ticket.number': ticket.number,
                                        'ticket.subject': ticket.subject,
                                        'agent.name': auth.user.name,
                                        'agent.first_name':
                                            auth.user.name.split(' ')[0],
                                    }}
                                />
                            </div>
                        )}
                    </div>
                </section>

                <aside className="order-3 hidden space-y-6 overflow-y-auto border-l p-4 xl:block">
                    <RequesterCard requester={requester} />
                    <RequesterTickets tickets={requesterTickets} />
                    <ActivityLog activity={activity} options={options} />
                </aside>
            </div>
        </>
    );
}

function RequesterCard({ requester }: { requester: Props['requester'] }) {
    const { t, localeTag } = useTranslation();
    const localTime = requester.timezone
        ? new Date().toLocaleTimeString(localeTag, {
              timeZone: requester.timezone,
              hour: '2-digit',
              minute: '2-digit',
          })
        : null;

    return (
        <section className="space-y-3">
            <div className="flex items-center gap-3">
                <UserAvatar
                    name={requester.name}
                    src={requester.avatar}
                    className="size-10"
                />
                <div className="min-w-0">
                    <p className="truncate font-medium">{requester.name}</p>
                    {requester.job_title && (
                        <p className="truncate text-xs text-muted-foreground">
                            {requester.job_title}
                        </p>
                    )}
                    <p className="text-xs text-muted-foreground">
                        {t('Customer since :year', {
                            year: new Date(requester.created_at).getFullYear(),
                        })}
                    </p>
                </div>
            </div>
            <dl className="space-y-1.5 text-sm">
                <div className="flex items-center gap-2 text-muted-foreground">
                    <Mail className="size-3.5 shrink-0" />
                    <a
                        href={`mailto:${requester.email}`}
                        className="truncate hover:text-foreground"
                    >
                        {requester.email}
                    </a>
                </div>
                {requester.phone && (
                    <div className="flex items-center gap-2 text-muted-foreground">
                        <Phone className="size-3.5 shrink-0" />
                        <a
                            href={`tel:${requester.phone}`}
                            className="truncate hover:text-foreground"
                        >
                            {requester.phone}
                        </a>
                    </div>
                )}
                {requester.organization && (
                    <div className="flex items-center gap-2 text-muted-foreground">
                        <Building2 className="size-3.5 shrink-0" />
                        <span className="truncate">
                            {requester.organization.name}
                        </span>
                    </div>
                )}
                {localTime && (
                    <div className="flex items-center gap-2 text-muted-foreground">
                        <Clock className="size-3.5 shrink-0" />
                        <span>
                            {t(':time local time', { time: localTime })}
                        </span>
                    </div>
                )}
            </dl>
        </section>
    );
}

function RequesterTickets({ tickets }: { tickets?: Ticket[] }) {
    const { t } = useTranslation();

    return (
        <section>
            <h2 className="mb-2 text-xs font-medium tracking-wide text-muted-foreground uppercase">
                {t('Other requests')}
            </h2>
            {tickets === undefined ? (
                <div className="space-y-2">
                    <Skeleton className="h-10 w-full" />
                    <Skeleton className="h-10 w-full" />
                </div>
            ) : tickets.length === 0 ? (
                <p className="text-sm text-muted-foreground">
                    {t('No other tickets.')}
                </p>
            ) : (
                <ul className="space-y-1">
                    {tickets.map((other) => (
                        <li key={other.id}>
                            <Link
                                href={show(other.id)}
                                prefetch
                                className="flex items-center gap-2 rounded-md px-2 py-1.5 text-sm hover:bg-accent"
                            >
                                <StatusBadge
                                    status={other.status}
                                    customStatus={other.custom_status}
                                    className="shrink-0"
                                />
                                <span className="truncate">
                                    {other.subject}
                                </span>
                            </Link>
                        </li>
                    ))}
                </ul>
            )}
        </section>
    );
}

const channelLabels: Record<string, string> = {
    portal: 'Portal',
    agent: 'Agent',
    api: 'API',
    email: 'Email',
    widget: 'Website widget',
};

const fieldLabels: Record<string, string> = {
    status: 'Status',
    ticket_status_id: 'Status',
    priority: 'Priority',
    type: 'Type',
    subject: 'Subject',
    assignee_id: 'Assignee',
    group_id: 'Group',
    category_id: 'Category',
};

function categoryName(options: TicketOptions, id: unknown): string | null {
    for (const category of options.categories) {
        if (category.id === id) {
            return category.name;
        }

        const child = category.children?.find((node) => node.id === id);

        if (child) {
            return `${category.name} › ${child.name}`;
        }
    }

    return null;
}

function ActivityLog({
    activity,
    options,
}: {
    activity?: Activity[];
    options: TicketOptions;
}) {
    const { t } = useTranslation();
    const describeValue = (field: string, value: unknown): string => {
        if (value === null || value === undefined || value === '') {
            return '—';
        }

        if (field === 'status') {
            return statusLabel(value as TicketStatus);
        }

        if (field === 'ticket_status_id') {
            return (
                options.customStatuses.find((status) => status.id === value)
                    ?.name ?? `#${displayValue(value)}`
            );
        }

        if (field === 'assignee_id') {
            return (
                options.agents.find((agent) => agent.id === value)?.name ??
                `#${displayValue(value)}`
            );
        }

        if (field === 'category_id') {
            return categoryName(options, value) ?? `#${displayValue(value)}`;
        }

        if (field === 'group_id') {
            return (
                options.groups.find((group) => group.id === value)?.name ??
                `#${displayValue(value)}`
            );
        }

        return displayValue(value);
    };

    const describeEvent = (entry: Activity): string => {
        const label = entry.secret ?? '';

        switch (entry.event) {
            case 'created':
                return t('created the ticket');
            case 'rated':
                return t('rated the support');
            case 'secret_requested':
                return t('requested the secret “:label”', { label });
            case 'secret_shared':
                return t('shared the secret “:label”', { label });
            case 'secret_submitted':
                return t('sent the secret “:label”', { label });
            case 'secret_viewed':
                return t('viewed the secret “:label”', { label });
            case 'secret_revoked':
                return t('revoked the secret “:label”', { label });
            case 'secret_expired':
                return t('expired the secret “:label”', { label });
            default:
                return t('updated');
        }
    };

    return (
        <section>
            <h2 className="mb-2 flex items-center gap-1.5 text-xs font-medium tracking-wide text-muted-foreground uppercase">
                <History className="size-3.5" /> {t('Activity')}
            </h2>
            {activity === undefined ? (
                <div className="space-y-2">
                    <Skeleton className="h-8 w-full" />
                    <Skeleton className="h-8 w-full" />
                </div>
            ) : (
                <ol className="relative space-y-3 border-l pl-4">
                    {activity.map((entry) => (
                        <li key={entry.id} className="text-xs">
                            <span className="absolute -left-[3.5px] mt-1.5 size-1.5 rounded-full bg-border" />
                            <p className="text-muted-foreground">
                                <span className="font-medium text-foreground">
                                    {entry.causer ??
                                        (entry.workflow
                                            ? t('Workflow: :name', {
                                                  name: entry.workflow,
                                              })
                                            : isCustomerSecretEvent(entry.event)
                                              ? t('Customer')
                                              : t('System'))}
                                </span>{' '}
                                {describeEvent(entry)} ·{' '}
                                {relativeTime(entry.created_at)}
                            </p>
                            {entry.event === 'rated' && (
                                <StarRating
                                    value={entry.score}
                                    size="sm"
                                    className="mt-1"
                                />
                            )}
                            {entry.event !== 'created' &&
                                entry.event !== 'rated' &&
                                !entry.event?.startsWith('secret_') && (
                                    <ul className="mt-1 space-y-0.5">
                                        {Object.entries(entry.changes.new)
                                            // The custom status already says which category it is in.
                                            .filter(
                                                ([field]) =>
                                                    field !== 'status' ||
                                                    !(
                                                        'ticket_status_id' in
                                                        entry.changes.new
                                                    ),
                                            )
                                            .map(([field, value]) => (
                                                <li key={field}>
                                                    {fieldLabels[field]
                                                        ? t(fieldLabels[field])
                                                        : field}
                                                    :{' '}
                                                    <span className="text-muted-foreground line-through">
                                                        {describeValue(
                                                            field,
                                                            entry.changes.old[
                                                                field
                                                            ],
                                                        )}
                                                    </span>{' '}
                                                    →{' '}
                                                    <span className="font-medium">
                                                        {describeValue(
                                                            field,
                                                            value,
                                                        )}
                                                    </span>
                                                </li>
                                            ))}
                                    </ul>
                                )}
                        </li>
                    ))}
                </ol>
            )}
        </section>
    );
}
