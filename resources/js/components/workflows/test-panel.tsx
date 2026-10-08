import { FlaskConical, X } from 'lucide-react';
import { useState } from 'react';
import { TicketSearch } from '@/components/tickets/ticket-search';
import type { TicketMatch } from '@/components/tickets/ticket-search';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { NODE_DEFINITIONS } from '@/components/workflows/node-catalog';
import { RunStatusBadge } from '@/components/workflows/run-status';
import { useTranslation } from '@/hooks/use-translation';
import type { SimulationResult } from '@/types';

/**
 * "Test on a ticket": walks the current (unsaved) graph against a real ticket without
 * changing anything, and lists what each step would do.
 */
export function TestPanel({
    result,
    running,
    onRun,
    onClose,
}: {
    result: SimulationResult | null;
    running: boolean;
    onRun: (ticket: TicketMatch) => void;
    onClose: () => void;
}) {
    const { t } = useTranslation();
    const [ticket, setTicket] = useState<TicketMatch | null>(null);

    return (
        <div className="flex min-h-0 flex-1 flex-col">
            <div className="flex items-start gap-3 border-b p-4">
                <span className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-emerald-500/15 text-emerald-600 dark:text-emerald-400">
                    <FlaskConical className="size-4.5" />
                </span>
                <div className="min-w-0 flex-1">
                    <h2 className="font-medium">{t('Test on a ticket')}</h2>
                    <p className="text-xs text-muted-foreground">
                        {t(
                            'Nothing is changed, sent or called. Waits are skipped.',
                        )}
                    </p>
                </div>
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    title={t('Close')}
                    onClick={onClose}
                >
                    <X />
                </Button>
            </div>
            <div className="min-h-0 flex-1 space-y-4 overflow-y-auto p-4">
                {ticket ? (
                    <div className="flex items-center justify-between gap-2 rounded-lg border p-2 text-sm">
                        <span className="truncate">
                            <span className="font-medium">{ticket.number}</span>{' '}
                            {ticket.subject}
                        </span>
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            onClick={() => setTicket(null)}
                        >
                            {t('Change')}
                        </Button>
                    </div>
                ) : (
                    <TicketSearch
                        excludeIds={[]}
                        onSelect={setTicket}
                        autoFocus
                    />
                )}
                <Button
                    type="button"
                    className="w-full"
                    disabled={!ticket || running}
                    onClick={() => ticket && onRun(ticket)}
                >
                    {running ? <Spinner /> : <FlaskConical />}
                    {t('Run test')}
                </Button>

                {result && (
                    <div className="space-y-2">
                        <div className="flex items-center gap-2">
                            <RunStatusBadge status={result.status} />
                            <span className="text-xs text-muted-foreground">
                                {t(':count step|:count steps', {
                                    count: result.steps.length,
                                })}
                            </span>
                        </div>
                        {result.error && (
                            <p className="text-sm text-destructive">
                                {result.error}
                            </p>
                        )}
                        <ol className="space-y-1.5">
                            {result.steps.map((step, index) => (
                                <li
                                    key={index}
                                    className="rounded-lg border p-2 text-xs"
                                >
                                    <p className="font-medium">
                                        {t(
                                            NODE_DEFINITIONS[step.node_type]
                                                ?.label ?? step.node_type,
                                        )}
                                        {step.handle &&
                                            step.handle !== 'out' && (
                                                <span className="text-muted-foreground">
                                                    {' '}
                                                    → {step.handle}
                                                </span>
                                            )}
                                        {step.iteration !== null && (
                                            <span className="text-muted-foreground">
                                                {' '}
                                                · #{step.iteration}
                                            </span>
                                        )}
                                    </p>
                                    {step.error ? (
                                        <p className="text-destructive">
                                            {step.error}
                                        </p>
                                    ) : (
                                        Object.keys(step.output).length > 0 && (
                                            <pre className="mt-1 break-all whitespace-pre-wrap text-muted-foreground">
                                                {JSON.stringify(
                                                    step.output,
                                                    null,
                                                    2,
                                                )}
                                            </pre>
                                        )
                                    )}
                                </li>
                            ))}
                        </ol>
                    </div>
                )}
            </div>
        </div>
    );
}
