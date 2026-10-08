import { useForm } from '@inertiajs/react';
import { KeyRound, Lock, MessageSquareReply, Paperclip, X } from 'lucide-react';
import { useRef, useState } from 'react';
import InputError from '@/components/input-error';
import {
    AiAssistMenu,
    AiSuggestionPanel,
    useAiAssistant,
} from '@/components/tickets/ai-assistant';
import type { AiFeatures } from '@/components/tickets/ai-assistant';
import { SecretDialog } from '@/components/secrets/secret-dialog';
import type { AttachedSecret } from '@/components/secrets/secret-dialog';
import { CannedResponsePicker } from '@/components/tickets/canned-response-picker';
import type {
    CannedResponseOption,
    PlaceholderValues,
} from '@/components/tickets/canned-response-picker';
import { RichTextEditor } from '@/components/tickets/rich-text-editor';
import type { RichTextEditorHandle } from '@/components/tickets/rich-text-editor';
import { AnimatedTabs } from '@/components/ui/animated-tabs';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { SplitButton } from '@/components/ui/split-button';
import { useTranslation } from '@/hooks/use-translation';
import { plainTextToHtml } from '@/lib/ai-stream';
import { formatFileSize, statusLabel } from '@/lib/tickets';
import type {
    CustomStatusOption,
    CustomStatusSummary,
    TicketStatus,
} from '@/types';

type Mode = 'public' | 'internal';

const SUBMIT_CATEGORIES: TicketStatus[] = [
    'open',
    'pending',
    'on_hold',
    'solved',
];

type Props = {
    action: string;
    currentStatus: TicketStatus;
    canReply: boolean;
    canAddNote: boolean;
    /** The ticket's custom status, which "Submit as" keeps by default. */
    currentCustomStatus?: CustomStatusSummary | null;
    /** Agents choose the status to apply when submitting ("Submit as Pending"). */
    customStatuses?: CustomStatusOption[];
    /** The status the agent last picked from the "Submit as" menu; it leads when still offered. */
    preferredStatusId?: number | null;
    /** Canned responses offered in the composer (agents only); undefined while loading. */
    cannedResponses?: CannedResponseOption[];
    /** Values for canned response placeholders. Pass it to enable the picker. */
    placeholderValues?: PlaceholderValues;
    /** Page props to reload after sending (everything when omitted). */
    reloadProps?: string[];
    /** Turns on the @mention, #ticket and /canned response popups in the editor. */
    ticketId?: number;
    /** Offers requesting and sharing secrets with public replies (agents with the permission). */
    canUseSecrets?: boolean;
    /** Told whether a draft is being written, as typing happens and after sending (collision detection). */
    onDraftChange?: (hasDraft: boolean) => void;
    /** AI assistant features the agent can use on this ticket (needs ticketId). */
    ai?: AiFeatures;
    /** The agent's signature (plain text), added under public replies unless turned off. */
    signature?: string | null;
};

/**
 * Whether the editor holds anything beyond empty paragraphs.
 */
function hasContent(html: string): boolean {
    return (
        /<img\b/i.test(html) ||
        html.replace(/<[^>]*>|&nbsp;/g, '').trim() !== ''
    );
}

/**
 * Reply / internal note composer with Zendesk-style "Submit as <status>".
 */
export function ReplyComposer({
    action,
    currentStatus,
    canReply,
    canAddNote,
    currentCustomStatus = null,
    customStatuses = [],
    preferredStatusId = null,
    cannedResponses,
    placeholderValues,
    reloadProps,
    ticketId,
    canUseSecrets = false,
    onDraftChange,
    ai,
    signature = null,
}: Props) {
    const { t } = useTranslation();
    const [mode, setMode] = useState<Mode>(canReply ? 'public' : 'internal');
    const [withSignature, setWithSignature] = useState(true);
    const addsSignature =
        mode === 'public' && withSignature && Boolean(signature?.trim());
    const fileInput = useRef<HTMLInputElement>(null);
    const editor = useRef<RichTextEditorHandle>(null);
    const assistant = useAiAssistant(ticketId ?? 0);

    const form = useForm<{
        body: string;
        is_internal: boolean;
        ticket_status_id: number | null;
        remember_status: boolean;
        attachments: File[];
        secrets: AttachedSecret[];
        ai_assisted: boolean;
    }>({
        body: '',
        is_internal: !canReply,
        ticket_status_id: null,
        remember_status: false,
        attachments: [],
        secrets: [],
        ai_assisted: false,
    });
    const hasDraft = hasContent(form.data.body);

    // Replies can't leave a ticket New or Closed.
    const statusChoices = customStatuses.filter(
        (status) =>
            status.is_active && SUBMIT_CATEGORIES.includes(status.category),
    );
    const defaultCategory: TicketStatus =
        currentStatus === 'new'
            ? 'open'
            : currentStatus === 'closed'
              ? 'solved'
              : currentStatus;
    const defaultStatus =
        statusChoices.find((status) => status.id === preferredStatusId) ??
        statusChoices.find((status) => status.id === currentCustomStatus?.id) ??
        statusChoices.find(
            (status) =>
                status.is_default && status.category === defaultCategory,
        ) ??
        null;

    const submit = (
        status: CustomStatusOption | null,
        rememberStatus = false,
    ) => {
        form.transform((data) => ({
            ...data,
            body:
                addsSignature && hasContent(data.body)
                    ? data.body + plainTextToHtml(`-- \n${signature}`)
                    : data.body,
            is_internal: mode === 'internal',
            ticket_status_id: status?.id ?? null,
            remember_status: rememberStatus,
            // Secrets only go out with public replies.
            secrets:
                mode === 'internal'
                    ? []
                    : data.secrets.map(({ token }) => token),
        }));

        form.post(action, {
            preserveScroll: true,
            // Keep the thread mounted so it can scroll to the new message.
            preserveState: true,
            forceFormData: form.data.attachments.length > 0,
            ...(reloadProps ? { only: reloadProps } : {}),
            onSuccess: () => {
                form.reset();
                assistant.dismiss();
                onDraftChange?.(false);
            },
        });
    };

    if (!canReply && !canAddNote) {
        return null;
    }

    const tabs = [
        ...(canReply
            ? [
                  {
                      id: 'public',
                      label: t('Public reply'),
                      icon: <MessageSquareReply className="size-3.5" />,
                  },
              ]
            : []),
        ...(canAddNote
            ? [
                  {
                      id: 'internal',
                      label: t('Internal note'),
                      icon: <Lock className="size-3.5" />,
                  },
              ]
            : []),
    ];

    return (
        <div className="space-y-3">
            <AnimatedTabs
                tabs={tabs}
                activeTab={mode}
                onChange={(id) => setMode(id as Mode)}
                renderContent={false}
                layoutId="reply-mode"
                variant="pill"
            />

            {ai && (
                <AiSuggestionPanel
                    assistant={assistant}
                    hasDraft={hasDraft}
                    onReplace={(text) => {
                        editor.current?.setContent(plainTextToHtml(text));
                        form.setData('ai_assisted', true);
                    }}
                    onInsert={(text) => {
                        editor.current?.insertContent(plainTextToHtml(text));
                        form.setData('ai_assisted', true);
                    }}
                />
            )}

            <RichTextEditor
                ref={editor}
                value={form.data.body}
                onChange={(html) => {
                    form.setData('body', html);
                    onDraftChange?.(hasContent(html));
                }}
                tone={mode === 'internal' ? 'internal' : 'default'}
                placeholder={
                    mode === 'internal'
                        ? t(
                              'Write an internal note — only your team can see it…',
                          )
                        : t('Write your reply to the requester…')
                }
                onSubmit={() =>
                    submit(mode === 'internal' ? null : defaultStatus)
                }
                suggestions={
                    ticketId === undefined
                        ? undefined
                        : {
                              isInternal: mode === 'internal',
                              currentTicketId: ticketId,
                              cannedResponses,
                              placeholderValues,
                          }
                }
            />
            {ticketId !== undefined && (
                <p className="-mt-1.5 text-xs text-muted-foreground">
                    {mode === 'internal'
                        ? t(
                              'Type @ to mention a teammate, # for a ticket or / for a canned response.',
                          )
                        : t('Type # for a ticket or / for a canned response.')}
                </p>
            )}
            <InputError message={form.errors.body} />

            {mode === 'public' && signature?.trim() && (
                <label className="flex items-start gap-2 text-xs text-muted-foreground">
                    <Checkbox
                        checked={withSignature}
                        onCheckedChange={(checked) =>
                            setWithSignature(checked === true)
                        }
                        className="mt-0.5"
                    />
                    <span className="min-w-0">
                        <span className="font-medium text-foreground">
                            {t('Add my signature')}
                        </span>{' '}
                        <span className="line-clamp-1 whitespace-pre-line">
                            {signature}
                        </span>
                    </span>
                </label>
            )}

            {form.data.attachments.length > 0 && (
                <ul className="flex flex-wrap gap-2">
                    {form.data.attachments.map((file, index) => (
                        <li
                            key={`${file.name}-${index}`}
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
                                            (_, i) => i !== index,
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

            {mode === 'public' && form.data.secrets.length > 0 && (
                <ul className="flex flex-wrap gap-2">
                    {form.data.secrets.map((secret) => (
                        <li
                            key={secret.token}
                            className="inline-flex items-center gap-1.5 rounded-md border border-primary/30 bg-primary/5 py-1 pr-1 pl-2 text-xs"
                        >
                            <KeyRound className="size-3" />
                            <span className="max-w-48 truncate">
                                {secret.label}
                            </span>
                            <span className="text-muted-foreground">
                                {secret.kind === 'request'
                                    ? t('Secret requested')
                                    : t('Secret shared')}
                            </span>
                            <button
                                type="button"
                                aria-label={t('Remove :name', {
                                    name: secret.label,
                                })}
                                onClick={() =>
                                    form.setData(
                                        'secrets',
                                        form.data.secrets.filter(
                                            (item) =>
                                                item.token !== secret.token,
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
            <InputError message={form.errors.secrets} />

            <div className="flex flex-wrap items-center justify-between gap-2">
                <div className="flex items-center gap-2">
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
                        <Paperclip /> {t('Attach')}
                    </Button>
                    {canUseSecrets &&
                        ticketId !== undefined &&
                        mode === 'public' && (
                            <SecretDialog
                                ticketId={ticketId}
                                onAttach={(secret) =>
                                    form.setData('secrets', [
                                        ...form.data.secrets,
                                        secret,
                                    ])
                                }
                            />
                        )}
                    {ai && ticketId !== undefined && (
                        <AiAssistMenu
                            ticketId={ticketId}
                            features={ai}
                            mode={mode}
                            hasDraft={hasDraft}
                            busy={assistant.state.status === 'streaming'}
                            onDraft={(options) =>
                                assistant.run({
                                    kind: 'draft',
                                    mode,
                                    ...options,
                                })
                            }
                            onImprove={(action, instruction) =>
                                assistant.run({
                                    kind: 'improve',
                                    draft: form.data.body,
                                    action,
                                    instruction,
                                })
                            }
                        />
                    )}
                    {placeholderValues && (
                        <CannedResponsePicker
                            responses={cannedResponses}
                            values={placeholderValues}
                            onInsert={(html) =>
                                editor.current?.insertContent(html)
                            }
                        />
                    )}
                    <span className="hidden text-xs text-muted-foreground sm:inline">
                        {t('⌘↵ to submit')}
                    </span>
                </div>

                {mode === 'internal' ? (
                    <Button
                        type="button"
                        disabled={form.processing}
                        onClick={() => submit(null)}
                        className="bg-amber-500 text-white hover:bg-amber-500/90"
                    >
                        <Lock /> {t('Add note')}
                    </Button>
                ) : (
                    <SplitButton
                        label={t('Submit as :status', {
                            status:
                                defaultStatus?.name ??
                                statusLabel(defaultCategory),
                        })}
                        disabled={form.processing}
                        onClick={() => submit(defaultStatus)}
                        options={statusChoices.map((status) => ({
                            value: String(status.id),
                            label: t('Submit as :status', {
                                status: status.name,
                            }),
                            onSelect: () => submit(status, true),
                        }))}
                    />
                )}
            </div>
        </div>
    );
}
