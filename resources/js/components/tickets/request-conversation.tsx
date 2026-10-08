import { useForm } from '@inertiajs/react';
import { Paperclip, X } from 'lucide-react';
import { useRef } from 'react';
import InputError from '@/components/input-error';
import { MessageThread } from '@/components/tickets/message-thread';
import { RichTextEditor } from '@/components/tickets/rich-text-editor';
import { StatusBadge } from '@/components/tickets/status-badge';
import { Button } from '@/components/ui/button';
import { useTranslation } from '@/hooks/use-translation';
import { formatDateTime, formatFileSize } from '@/lib/tickets';
import type { Ticket, TicketMessage } from '@/types';

/**
 * A request as its requester (or someone copied) sees it: header, public thread and reply
 * box. Used by the signed-in portal and by guests following a magic link.
 */
export function RequestConversation({
    ticket,
    messages,
    earlierMessages = 0,
    canReply,
    replyUrl,
}: {
    ticket: Ticket;
    messages: TicketMessage[];
    earlierMessages?: number;
    canReply: boolean;
    replyUrl: string;
}) {
    const { t } = useTranslation();
    const fileInput = useRef<HTMLInputElement>(null);
    const form = useForm({
        body: '',
        mark_solved: false,
        attachments: [] as File[],
    });

    const submit = (markSolved: boolean) => {
        form.transform((data) => ({ ...data, mark_solved: markSolved }));
        form.post(replyUrl, {
            preserveScroll: true,
            // Keep the thread mounted so it can scroll to the new message.
            preserveState: true,
            forceFormData: form.data.attachments.length > 0,
            onSuccess: () => form.reset(),
        });
    };

    const isResolved = ticket.status === 'solved' || ticket.status === 'closed';

    return (
        <>
            <header className="space-y-2">
                <div className="flex items-center gap-2 text-xs text-muted-foreground">
                    <StatusBadge
                        status={
                            ticket.status === 'new' ? 'open' : ticket.status
                        }
                    />
                    <span>
                        {t('Request :number', { number: ticket.number })}
                    </span>
                    <span>·</span>
                    <span>
                        {t('Submitted :date', {
                            date: formatDateTime(ticket.created_at),
                        })}
                    </span>
                </div>
                <h1 className="text-2xl font-semibold tracking-tight text-balance">
                    {ticket.subject}
                </h1>
                {ticket.assignee && (
                    <p className="text-sm text-muted-foreground">
                        {t(':name is handling your request.', {
                            name: ticket.assignee.name,
                        })}
                    </p>
                )}
                {(ticket.collaborators ?? []).length > 0 && (
                    <p className="text-sm text-muted-foreground">
                        {t('Copied: :names', {
                            names: (ticket.collaborators ?? [])
                                .map((collaborator) => collaborator.name)
                                .join(', '),
                        })}
                    </p>
                )}
            </header>

            <MessageThread
                messages={messages}
                requesterId={ticket.requester?.id}
                earlierCount={earlierMessages}
            />

            {canReply ? (
                <div className="space-y-3 rounded-xl border bg-card p-4 shadow-xs">
                    <RichTextEditor
                        value={form.data.body}
                        onChange={(html) => form.setData('body', html)}
                        placeholder={
                            isResolved
                                ? t('Reply to reopen this request…')
                                : t('Add a reply…')
                        }
                        onSubmit={() => submit(false)}
                    />
                    <InputError message={form.errors.body} />

                    {form.data.attachments.length > 0 && (
                        <ul className="flex flex-wrap gap-2">
                            {form.data.attachments.map((file, i) => (
                                <li
                                    key={`${file.name}-${i}`}
                                    className="inline-flex items-center gap-1.5 rounded-md border bg-muted/50 py-1 pr-1 pl-2 text-xs"
                                >
                                    <Paperclip className="size-3" />
                                    <span className="max-w-40 truncate">
                                        {file.name}
                                    </span>
                                    <span className="text-muted-foreground">
                                        {formatFileSize(file.size)}
                                    </span>
                                    <button
                                        type="button"
                                        aria-label={t('Remove :name', {
                                            name: file.name,
                                        })}
                                        onClick={() =>
                                            form.setData(
                                                'attachments',
                                                form.data.attachments.filter(
                                                    (_, j) => j !== i,
                                                ),
                                            )
                                        }
                                        className="rounded p-0.5 hover:bg-background"
                                    >
                                        <X className="size-3" />
                                    </button>
                                </li>
                            ))}
                        </ul>
                    )}

                    <div className="flex flex-wrap items-center justify-between gap-2">
                        <input
                            ref={fileInput}
                            type="file"
                            multiple
                            className="hidden"
                            onChange={(event) => {
                                form.setData('attachments', [
                                    ...form.data.attachments,
                                    ...Array.from(event.target.files ?? []),
                                ]);
                                event.target.value = '';
                            }}
                        />
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            onClick={() => fileInput.current?.click()}
                        >
                            <Paperclip /> {t('Attach files')}
                        </Button>
                        <div className="flex gap-2">
                            {!isResolved && (
                                <Button
                                    type="button"
                                    variant="outline"
                                    disabled={form.processing}
                                    onClick={() => submit(true)}
                                >
                                    {t('Reply & mark as solved')}
                                </Button>
                            )}
                            <Button
                                type="button"
                                disabled={form.processing}
                                onClick={() => submit(false)}
                            >
                                {t('Send reply')}
                            </Button>
                        </div>
                    </div>
                </div>
            ) : (
                <p className="rounded-lg border border-dashed p-4 text-center text-sm text-muted-foreground">
                    {t(
                        'This request is closed. Need more help? Submit a new request and reference :number.',
                        { number: ticket.number },
                    )}
                </p>
            )}
        </>
    );
}
