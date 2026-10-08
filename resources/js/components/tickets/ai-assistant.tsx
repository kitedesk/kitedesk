import {
    Check,
    RotateCcw,
    Sparkles,
    Square,
    TextCursorInput,
    X,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useTranslation } from '@/hooks/use-translation';
import { AssistantError, streamAssistant } from '@/lib/ai-stream';
import {
    articles as articlesRoute,
    draft as draftRoute,
    improve as improveRoute,
} from '@/routes/agent/tickets/ai';

export type AiFeatures = {
    summaries: boolean;
    drafts: boolean;
    improve: boolean;
};

type ImproveAction =
    | 'improve'
    | 'fix_grammar'
    | 'shorten'
    | 'friendlier'
    | 'formal'
    | 'custom';

type AssistantRequest =
    | {
          kind: 'draft';
          mode: 'public' | 'internal';
          article_ids?: number[];
          instruction?: string;
      }
    | {
          kind: 'improve';
          draft: string;
          action: ImproveAction;
          instruction?: string;
      };

type RelatedArticle = {
    id: number;
    title: string;
    excerpt: string | null;
    url: string;
};

/**
 * Runs assistant requests for a ticket and keeps the streamed suggestion until the agent uses
 * or discards it.
 */
export function useAiAssistant(ticketId: number) {
    const { t } = useTranslation();
    const [state, setState] = useState<
        | { status: 'idle' }
        | {
              status: 'streaming' | 'done' | 'error';
              request: AssistantRequest;
              text: string;
              error?: string;
          }
    >({ status: 'idle' });
    const controller = useRef<AbortController | null>(null);

    useEffect(() => () => controller.current?.abort(), []);

    const run = async (request: AssistantRequest) => {
        controller.current?.abort();
        const abort = new AbortController();
        controller.current = abort;
        setState({ status: 'streaming', request, text: '' });

        const { kind, ...body } = request;

        try {
            await streamAssistant(
                kind === 'draft'
                    ? draftRoute.url(ticketId)
                    : improveRoute.url(ticketId),
                body,
                {
                    signal: abort.signal,
                    messages: {
                        failed: t(
                            'The AI assistant could not answer. Try again, or ask an admin to check the AI settings.',
                        ),
                        rateLimited: t(
                            'Too many AI requests. Wait a minute and try again.',
                        ),
                    },
                    onText: (chunk) =>
                        setState((current) =>
                            current.status === 'streaming'
                                ? { ...current, text: current.text + chunk }
                                : current,
                        ),
                },
            );
            setState((current) =>
                current.status === 'streaming'
                    ? { ...current, status: 'done' }
                    : current,
            );
        } catch (error) {
            if (abort.signal.aborted) {
                return;
            }

            setState((current) =>
                current.status === 'idle'
                    ? current
                    : {
                          ...current,
                          status: 'error',
                          error:
                              error instanceof AssistantError
                                  ? error.message
                                  : t('Something went wrong.'),
                      },
            );
        }
    };

    return {
        state,
        run,
        retry: () => state.status !== 'idle' && run(state.request),
        /** Keeps what was streamed so far. */
        stop: () => {
            controller.current?.abort();
            setState((current) =>
                current.status === 'streaming'
                    ? { ...current, status: 'done' }
                    : current,
            );
        },
        dismiss: () => {
            controller.current?.abort();
            setState({ status: 'idle' });
        },
    };
}

/**
 * The composer's ✨ menu: draft a reply (optionally from articles or with instructions) or
 * rewrite the current draft.
 */
export function AiAssistMenu({
    ticketId,
    features,
    mode,
    hasDraft,
    busy,
    onDraft,
    onImprove,
}: {
    ticketId: number;
    features: AiFeatures;
    mode: 'public' | 'internal';
    hasDraft: boolean;
    busy: boolean;
    onDraft: (options: {
        article_ids?: number[];
        instruction?: string;
    }) => void;
    onImprove: (action: ImproveAction, instruction?: string) => void;
}) {
    const { t } = useTranslation();
    const [dialog, setDialog] = useState<'draft' | 'rewrite' | null>(null);

    if (!features.drafts && !features.improve) {
        return null;
    }

    const improveActions: { action: ImproveAction; label: string }[] = [
        { action: 'improve', label: t('Improve writing') },
        { action: 'fix_grammar', label: t('Fix spelling and grammar') },
        { action: 'shorten', label: t('Make it shorter') },
        { action: 'friendlier', label: t('Make it friendlier') },
        { action: 'formal', label: t('Make it more formal') },
    ];

    return (
        <>
            <DropdownMenu>
                <DropdownMenuTrigger asChild>
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        disabled={busy}
                    >
                        <Sparkles /> {t('AI')}
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="start" className="w-60">
                    {features.drafts && (
                        <>
                            <DropdownMenuItem onSelect={() => onDraft({})}>
                                {mode === 'internal'
                                    ? t('Draft an internal note')
                                    : t('Draft a reply')}
                            </DropdownMenuItem>
                            <DropdownMenuItem
                                onSelect={() => setDialog('draft')}
                            >
                                {t('Draft with articles or instructions…')}
                            </DropdownMenuItem>
                        </>
                    )}
                    {features.drafts && features.improve && (
                        <DropdownMenuSeparator />
                    )}
                    {features.improve && (
                        <>
                            <DropdownMenuLabel className="text-xs font-normal text-muted-foreground">
                                {hasDraft
                                    ? t('Rewrite your draft')
                                    : t('Write a draft to rewrite it')}
                            </DropdownMenuLabel>
                            {improveActions.map(({ action, label }) => (
                                <DropdownMenuItem
                                    key={action}
                                    disabled={!hasDraft}
                                    onSelect={() => onImprove(action)}
                                >
                                    {label}
                                </DropdownMenuItem>
                            ))}
                            <DropdownMenuItem
                                disabled={!hasDraft}
                                onSelect={() => setDialog('rewrite')}
                            >
                                {t('Rewrite with instructions…')}
                            </DropdownMenuItem>
                        </>
                    )}
                </DropdownMenuContent>
            </DropdownMenu>

            {dialog === 'draft' && (
                <DraftOptionsDialog
                    ticketId={ticketId}
                    onClose={() => setDialog(null)}
                    onSubmit={(options) => {
                        setDialog(null);
                        onDraft(options);
                    }}
                />
            )}
            {dialog === 'rewrite' && (
                <InstructionDialog
                    onClose={() => setDialog(null)}
                    onSubmit={(instruction) => {
                        setDialog(null);
                        onImprove('custom', instruction);
                    }}
                />
            )}
        </>
    );
}

const textareaClass =
    'w-full rounded-md border bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30';

/**
 * Pick help center articles the draft should rely on, and add instructions.
 */
function DraftOptionsDialog({
    ticketId,
    onClose,
    onSubmit,
}: {
    ticketId: number;
    onClose: () => void;
    onSubmit: (options: {
        article_ids: number[];
        instruction?: string;
    }) => void;
}) {
    const { t } = useTranslation();
    const [articles, setArticles] = useState<RelatedArticle[] | null>(null);
    const [selected, setSelected] = useState<number[]>([]);
    const [instruction, setInstruction] = useState('');

    useEffect(() => {
        const abort = new AbortController();

        fetch(articlesRoute.url(ticketId), {
            headers: { Accept: 'application/json' },
            signal: abort.signal,
        })
            .then((response) =>
                response.ok ? response.json() : { articles: [] },
            )
            .then((data: { articles: RelatedArticle[] }) => {
                setArticles(data.articles);
                setSelected(data.articles.slice(0, 2).map(({ id }) => id));
            })
            .catch(() => {
                if (!abort.signal.aborted) {
                    setArticles([]);
                }
            });

        return () => abort.abort();
    }, [ticketId]);

    const toggle = (id: number, checked: boolean) =>
        setSelected((current) =>
            checked
                ? [...current, id].slice(-5)
                : current.filter((item) => item !== id),
        );

    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent className="sm:max-w-lg">
                <DialogTitle>{t('Draft a reply with AI')}</DialogTitle>
                <DialogDescription>
                    {t(
                        'The assistant reads the conversation. Pick help center articles it should rely on and link to.',
                    )}
                </DialogDescription>

                <div className="space-y-2">
                    <Label>{t('Related articles')}</Label>
                    {articles === null ? (
                        <div className="space-y-2">
                            {[0, 1, 2].map((row) => (
                                <div
                                    key={row}
                                    className="h-10 animate-pulse rounded-md bg-muted"
                                />
                            ))}
                        </div>
                    ) : articles.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            {t('No related articles found.')}
                        </p>
                    ) : (
                        <ul className="max-h-56 space-y-1 overflow-y-auto">
                            {articles.map((article) => (
                                <li key={article.id}>
                                    <label className="flex cursor-pointer items-start gap-2.5 rounded-md p-2 hover:bg-muted/60">
                                        <Checkbox
                                            className="mt-0.5"
                                            checked={selected.includes(
                                                article.id,
                                            )}
                                            onCheckedChange={(checked) =>
                                                toggle(
                                                    article.id,
                                                    checked === true,
                                                )
                                            }
                                        />
                                        <span className="min-w-0 text-sm">
                                            <span className="block font-medium">
                                                {article.title}
                                            </span>
                                            {article.excerpt && (
                                                <span className="line-clamp-2 text-xs text-muted-foreground">
                                                    {article.excerpt}
                                                </span>
                                            )}
                                        </span>
                                    </label>
                                </li>
                            ))}
                        </ul>
                    )}
                </div>

                <div className="space-y-2">
                    <Label htmlFor="ai-draft-instruction">
                        {t('Instructions (optional)')}
                    </Label>
                    <textarea
                        id="ai-draft-instruction"
                        rows={3}
                        maxLength={500}
                        value={instruction}
                        onChange={(event) => setInstruction(event.target.value)}
                        placeholder={t(
                            'e.g. Offer a refund and apologize for the delay.',
                        )}
                        className={textareaClass}
                    />
                </div>

                <DialogFooter>
                    <Button type="button" variant="outline" onClick={onClose}>
                        {t('Cancel')}
                    </Button>
                    <Button
                        type="button"
                        onClick={() =>
                            onSubmit({
                                article_ids: selected,
                                instruction: instruction.trim() || undefined,
                            })
                        }
                    >
                        <Sparkles /> {t('Generate draft')}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

function InstructionDialog({
    onClose,
    onSubmit,
}: {
    onClose: () => void;
    onSubmit: (instruction: string) => void;
}) {
    const { t } = useTranslation();
    const [instruction, setInstruction] = useState('');

    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent className="sm:max-w-md">
                <DialogTitle>{t('Rewrite with instructions')}</DialogTitle>
                <DialogDescription>
                    {t('Tell the assistant how to change your draft.')}
                </DialogDescription>
                <textarea
                    rows={3}
                    maxLength={500}
                    autoFocus
                    value={instruction}
                    onChange={(event) => setInstruction(event.target.value)}
                    placeholder={t(
                        'e.g. Translate it to Spanish, or turn the steps into a list.',
                    )}
                    className={textareaClass}
                />
                <DialogFooter>
                    <Button type="button" variant="outline" onClick={onClose}>
                        {t('Cancel')}
                    </Button>
                    <Button
                        type="button"
                        disabled={instruction.trim() === ''}
                        onClick={() => onSubmit(instruction.trim())}
                    >
                        <Sparkles /> {t('Rewrite')}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

/**
 * The streamed suggestion above the editor. Nothing touches the draft until the agent picks
 * Replace or Insert.
 */
export function AiSuggestionPanel({
    assistant,
    hasDraft,
    onReplace,
    onInsert,
}: {
    assistant: ReturnType<typeof useAiAssistant>;
    hasDraft: boolean;
    onReplace: (text: string) => void;
    onInsert: (text: string) => void;
}) {
    const { t } = useTranslation();
    const { state } = assistant;

    if (state.status === 'idle') {
        return null;
    }

    const streaming = state.status === 'streaming';
    const usable = state.status === 'done' && state.text.trim() !== '';

    return (
        <div className="rounded-lg border border-violet-300/60 bg-violet-50/60 p-3 dark:border-violet-500/30 dark:bg-violet-500/5">
            <div className="mb-2 flex items-center gap-2 text-xs font-medium text-violet-700 dark:text-violet-300">
                <Sparkles className="size-3.5" />
                {state.request.kind === 'improve'
                    ? t('Suggested rewrite')
                    : t('Suggested draft')}
                {streaming && <Spinner className="size-3" />}
                <button
                    type="button"
                    onClick={assistant.dismiss}
                    aria-label={t('Discard')}
                    className="ml-auto rounded p-0.5 text-muted-foreground hover:bg-background"
                >
                    <X className="size-3.5" />
                </button>
            </div>

            {state.status === 'error' ? (
                <p className="text-sm text-destructive">{state.error}</p>
            ) : (
                <div
                    className="max-h-64 overflow-y-auto text-sm whitespace-pre-wrap"
                    aria-live="polite"
                >
                    {state.text}
                    {streaming && (
                        <span className="ml-0.5 inline-block h-4 w-1.5 animate-pulse bg-violet-500 align-text-bottom" />
                    )}
                </div>
            )}

            <div className="mt-3 flex flex-wrap items-center gap-2">
                {streaming ? (
                    <Button
                        type="button"
                        size="sm"
                        variant="outline"
                        onClick={assistant.stop}
                    >
                        <Square /> {t('Stop')}
                    </Button>
                ) : (
                    <>
                        {usable && (
                            <Button
                                type="button"
                                size="sm"
                                onClick={() => {
                                    onReplace(state.text);
                                    assistant.dismiss();
                                }}
                            >
                                <Check />{' '}
                                {hasDraft ? t('Replace draft') : t('Use draft')}
                            </Button>
                        )}
                        {usable && hasDraft && (
                            <Button
                                type="button"
                                size="sm"
                                variant="outline"
                                onClick={() => {
                                    onInsert(state.text);
                                    assistant.dismiss();
                                }}
                            >
                                <TextCursorInput /> {t('Insert at cursor')}
                            </Button>
                        )}
                        <Button
                            type="button"
                            size="sm"
                            variant="ghost"
                            onClick={() => assistant.retry()}
                        >
                            <RotateCcw /> {t('Try again')}
                        </Button>
                        <Button
                            type="button"
                            size="sm"
                            variant="ghost"
                            onClick={assistant.dismiss}
                        >
                            {t('Discard')}
                        </Button>
                    </>
                )}
                <span className="ml-auto text-[11px] text-muted-foreground">
                    {t('AI can make mistakes. Review before sending.')}
                </span>
            </div>
        </div>
    );
}
