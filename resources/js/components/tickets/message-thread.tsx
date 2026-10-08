import { router, usePage } from '@inertiajs/react';
import { m } from 'framer-motion';
import { Forward, Lock, Sparkles } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { SecretCard } from '@/components/secrets/secret-card';
import { MessageAttachments } from '@/components/tickets/message-attachments';
import { UserAvatar } from '@/components/tickets/user-avatar';
import { Button } from '@/components/ui/button';
import { useTranslation } from '@/hooks/use-translation';
import { formatDateTime, relativeTime } from '@/lib/tickets';
import { cn } from '@/lib/utils';
import type { TicketMessage } from '@/types';

/**
 * Conversation timeline. Message bodies are sanitized server-side (HTML Purifier)
 * before storage, which is what makes rendering them as HTML safe.
 *
 * When the current user adds a message, the page scrolls to it. Messages arriving
 * live from other people don't move the page, so nobody loses their place mid-reply.
 */
export function MessageThread({
    messages,
    requesterId,
    earlierCount = 0,
}: {
    messages: TicketMessage[];
    requesterId?: number;
    /** Older messages left out of `messages`; a button loads the whole conversation. */
    earlierCount?: number;
}) {
    const { t, tChoice } = useTranslation();
    const currentUserId = usePage().props.auth.user?.id;
    const list = useRef<HTMLOListElement>(null);
    const lastSeenId = useRef(Math.max(0, ...messages.map((m) => m.id)));
    // Messages already there on first render appear as-is; only later ones animate in.
    const [initialLastId] = useState(() =>
        Math.max(0, ...messages.map((message) => message.id)),
    );

    useEffect(() => {
        const added = messages.filter(
            (message) => message.id > lastSeenId.current,
        );

        if (added.length === 0) {
            return;
        }

        lastSeenId.current = Math.max(...added.map((message) => message.id));

        const own = added.filter(
            (message) => message.author?.id === currentUserId,
        );

        if (own.length === 0) {
            return;
        }

        const reduceMotion = window.matchMedia(
            '(prefers-reduced-motion: reduce)',
        ).matches;

        list.current
            ?.querySelector(`[data-message-id="${own[own.length - 1].id}"]`)
            ?.scrollIntoView({
                behavior: reduceMotion ? 'auto' : 'smooth',
                block: 'start',
            });
    }, [messages, currentUserId]);

    return (
        <div className="space-y-4">
            {earlierCount > 0 && (
                <div className="flex justify-center">
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        onClick={() =>
                            router.reload({
                                data: { thread_all: 1 },
                                only: ['messages', 'earlierMessages'],
                            })
                        }
                    >
                        {tChoice(
                            'Show :count earlier message|Show :count earlier messages',
                            earlierCount,
                        )}
                    </Button>
                </div>
            )}
            <ol ref={list} className="space-y-4">
                {messages.map((message) => {
                    const isRequester = message.author?.id === requesterId;

                    return (
                        <m.li
                            key={message.id}
                            data-message-id={message.id}
                            initial={
                                message.id > initialLastId
                                    ? { opacity: 0, y: 6 }
                                    : false
                            }
                            animate={{ opacity: 1, y: 0 }}
                            transition={{
                                type: 'spring',
                                stiffness: 260,
                                damping: 28,
                            }}
                            className="flex scroll-mt-24 gap-3"
                        >
                            <UserAvatar
                                src={message.author?.avatar}
                                name={
                                    message.author?.name ??
                                    (message.workflow
                                        ? t('Workflow')
                                        : t('System'))
                                }
                                className="mt-0.5"
                            />
                            <div
                                className={cn(
                                    'min-w-0 flex-1 rounded-xl border bg-card px-4 py-3 shadow-xs',
                                    message.is_internal &&
                                        'border-amber-300/70 bg-amber-50 dark:border-amber-500/30 dark:bg-amber-500/5',
                                )}
                            >
                                <div className="mb-1.5 flex flex-wrap items-center gap-x-2 gap-y-1 text-sm">
                                    <span className="font-semibold">
                                        {message.author?.name ??
                                            (message.workflow
                                                ? t('Workflow: :name', {
                                                      name: message.workflow,
                                                  })
                                                : t('System'))}
                                    </span>
                                    {isRequester && (
                                        <span className="text-xs text-muted-foreground">
                                            {t('Requester')}
                                        </span>
                                    )}
                                    {message.is_internal && (
                                        <span className="inline-flex items-center gap-1 rounded bg-amber-500/15 px-1.5 py-0.5 text-[11px] font-medium text-amber-700 dark:text-amber-300">
                                            <Lock className="size-3" />{' '}
                                            {t('Internal note')}
                                        </span>
                                    )}
                                    {message.forwarded_to && (
                                        <span className="inline-flex items-center gap-1 rounded bg-sky-500/15 px-1.5 py-0.5 text-[11px] font-medium text-sky-700 dark:text-sky-300">
                                            <Forward className="size-3" />{' '}
                                            {t('Forwarded to :name', {
                                                name: message.forwarded_to,
                                            })}
                                        </span>
                                    )}
                                    {(message.ai_assisted ||
                                        message.via_mcp) && (
                                        <span
                                            className="inline-flex items-center gap-1 rounded bg-violet-500/15 px-1.5 py-0.5 text-[11px] font-medium text-violet-700 dark:text-violet-300"
                                            title={
                                                message.via_mcp
                                                    ? t(
                                                          'Sent by an AI app connected to this agent’s account',
                                                      )
                                                    : t(
                                                          'Drafted with the AI assistant and reviewed by the agent',
                                                      )
                                            }
                                        >
                                            <Sparkles className="size-3" />{' '}
                                            {message.via_mcp
                                                ? t('Via AI app')
                                                : t('AI-assisted')}
                                        </span>
                                    )}
                                    <time
                                        dateTime={message.created_at}
                                        title={formatDateTime(
                                            message.created_at,
                                        )}
                                        className="ml-auto text-xs text-muted-foreground"
                                    >
                                        {relativeTime(message.created_at)}
                                    </time>
                                </div>
                                <div
                                    className="prose-ticket"
                                    dangerouslySetInnerHTML={{
                                        __html: message.body,
                                    }}
                                />
                                {message.attachments && (
                                    <MessageAttachments
                                        attachments={message.attachments}
                                    />
                                )}
                                {message.secrets?.map((secret) => (
                                    <SecretCard
                                        key={secret.token}
                                        secret={secret}
                                        requestedBy={message.author?.name}
                                    />
                                ))}
                            </div>
                        </m.li>
                    );
                })}
            </ol>
        </div>
    );
}
