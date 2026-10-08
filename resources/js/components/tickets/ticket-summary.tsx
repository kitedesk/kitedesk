import { RefreshCw, Sparkles, X } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';
import { Button } from '@/components/ui/button';
import { useTranslation } from '@/hooks/use-translation';
import { relativeTime } from '@/lib/tickets';
import { summary as summaryRoute } from '@/routes/agent/tickets/ai';

type Summary = { summary: string; generated_at: string; message_id: number };

/**
 * The "Summarize" button for the ticket header.
 */
export function SummarizeButton({ onClick }: { onClick: () => void }) {
    const { t } = useTranslation();

    return (
        <Button
            variant="ghost"
            size="sm"
            onClick={onClick}
            title={t('Summarize with AI')}
        >
            <Sparkles />{' '}
            <span className="hidden sm:inline">{t('Summarize')}</span>
        </Button>
    );
}

/**
 * An AI briefing on the ticket, shown above the conversation. Summaries are cached on the
 * server until the next message, so reopening it is instant.
 */
export function TicketSummary({
    ticketId,
    latestMessageId,
    onClose,
}: {
    ticketId: number;
    latestMessageId: number | null;
    onClose: () => void;
}) {
    const { t } = useTranslation();
    const [summary, setSummary] = useState<Summary | null>(null);
    const [error, setError] = useState<string | null>(null);
    const [loading, setLoading] = useState(true);
    const controller = useRef<AbortController | null>(null);

    const load = useCallback(
        async (refresh: boolean) => {
            controller.current?.abort();
            const abort = new AbortController();
            controller.current = abort;
            setLoading(true);
            setError(null);

            try {
                const response = await fetch(
                    summaryRoute.url(ticketId, {
                        query: refresh ? { refresh: 1 } : {},
                    }),
                    {
                        headers: { Accept: 'application/json' },
                        signal: abort.signal,
                    },
                );
                const data = await response.json().catch(() => ({}));

                if (!response.ok) {
                    setError(
                        response.status === 429
                            ? t(
                                  'Too many AI requests. Wait a minute and try again.',
                              )
                            : String(
                                  data.message ||
                                      t(
                                          'The AI assistant could not answer. Try again, or ask an admin to check the AI settings.',
                                      ),
                              ),
                    );
                } else {
                    setSummary(data as Summary);
                }
            } catch {
                if (!abort.signal.aborted) {
                    setError(t('Something went wrong.'));
                }
            } finally {
                if (!abort.signal.aborted) {
                    setLoading(false);
                }
            }
        },
        [ticketId, t],
    );

    useEffect(() => {
        void load(false);

        return () => controller.current?.abort();
    }, [load]);

    const stale =
        summary !== null &&
        latestMessageId !== null &&
        summary.message_id !== latestMessageId;

    return (
        <section className="mb-6 rounded-xl border border-violet-300/60 bg-violet-50/60 p-4 dark:border-violet-500/30 dark:bg-violet-500/5">
            <div className="mb-2 flex items-center gap-2 text-sm font-medium text-violet-700 dark:text-violet-300">
                <Sparkles className="size-4" />
                {t('AI summary')}
                <div className="ml-auto flex items-center gap-1">
                    <Button
                        variant="ghost"
                        size="sm"
                        className="h-7"
                        disabled={loading}
                        onClick={() => void load(true)}
                    >
                        <RefreshCw
                            className={loading ? 'animate-spin' : undefined}
                        />{' '}
                        {t('Refresh')}
                    </Button>
                    <button
                        type="button"
                        onClick={onClose}
                        aria-label={t('Close')}
                        className="rounded p-1 text-muted-foreground hover:bg-background"
                    >
                        <X className="size-4" />
                    </button>
                </div>
            </div>

            {loading && summary === null ? (
                <div className="space-y-2 py-1" aria-busy>
                    {['w-11/12', 'w-4/5', 'w-2/3'].map((width) => (
                        <div
                            key={width}
                            className={`h-3.5 animate-pulse rounded bg-violet-200/70 dark:bg-violet-500/20 ${width}`}
                        />
                    ))}
                </div>
            ) : error ? (
                <p className="text-sm text-destructive">{error}</p>
            ) : (
                summary && (
                    <>
                        <div className="text-sm whitespace-pre-wrap">
                            {summary.summary}
                        </div>
                        <p className="mt-2 text-xs text-muted-foreground">
                            {stale
                                ? t(
                                      'New messages since this summary. Refresh to update it.',
                                  )
                                : t('Generated :time. AI can make mistakes.', {
                                      time: relativeTime(summary.generated_at),
                                  })}
                        </p>
                    </>
                )
            )}
        </section>
    );
}
