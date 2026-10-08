import { Link, router } from '@inertiajs/react';
import { History, RefreshCw } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { RunStatusBadge } from '@/components/workflows/run-status';
import { useTranslation } from '@/hooks/use-translation';
import { formatDateTime, relativeTime } from '@/lib/tickets';
import { cn } from '@/lib/utils';
import { show } from '@/routes/agent/tickets';
import { cancel } from '@/routes/admin/workflows/runs';
import type { WorkflowRunSummary } from '@/types';

/**
 * The workflow's recent runs. Selecting one replays its path on the canvas.
 */
export function ExecutionsPanel({
    workflowId,
    runs,
    selectedId,
}: {
    workflowId: number | null;
    runs: WorkflowRunSummary[];
    selectedId: number | null;
}) {
    const { t } = useTranslation();
    const [refreshing, setRefreshing] = useState(false);

    const select = (id: number) =>
        router.reload({ data: { run: id }, only: ['selectedRun'] });

    if (workflowId === null) {
        return (
            <p className="p-2 text-sm text-muted-foreground">
                {t('Save the workflow to see its runs here.')}
            </p>
        );
    }

    return (
        <div className="flex min-h-0 flex-1 flex-col gap-2">
            <div className="flex items-center justify-between">
                <h2 className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                    {t('Recent runs')}
                </h2>
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    className="size-7"
                    title={t('Refresh')}
                    disabled={refreshing}
                    onClick={() =>
                        router.reload({
                            only: ['runs', 'selectedRun'],
                            onStart: () => setRefreshing(true),
                            onFinish: () => setRefreshing(false),
                        })
                    }
                >
                    <RefreshCw
                        className={cn('size-3.5', refreshing && 'animate-spin')}
                    />
                </Button>
            </div>
            {runs.length === 0 ? (
                <div className="rounded-lg border border-dashed px-3 py-8 text-center text-sm text-muted-foreground">
                    <History className="mx-auto mb-2 size-5" />
                    {t('No runs yet.')}
                </div>
            ) : (
                <ol className="-mx-1 min-h-0 flex-1 space-y-1 overflow-y-auto px-1">
                    {runs.map((run) => (
                        <li key={run.id}>
                            <div
                                role="button"
                                tabIndex={0}
                                onClick={() => select(run.id)}
                                onKeyDown={(event) =>
                                    event.key === 'Enter' && select(run.id)
                                }
                                className={cn(
                                    'cursor-pointer space-y-1 rounded-lg border p-2 text-sm transition-colors hover:bg-accent',
                                    selectedId === run.id &&
                                        'border-primary bg-accent',
                                )}
                            >
                                <div className="flex items-center justify-between gap-2">
                                    <RunStatusBadge status={run.status} />
                                    <span
                                        className="text-xs text-muted-foreground"
                                        title={formatDateTime(run.started_at)}
                                    >
                                        {relativeTime(run.started_at)}
                                    </span>
                                </div>
                                <Link
                                    href={show(run.ticket.id)}
                                    onClick={(event) => event.stopPropagation()}
                                    className="block truncate text-xs hover:underline"
                                >
                                    <span className="font-medium">
                                        {run.ticket.number}
                                    </span>{' '}
                                    {run.ticket.subject}
                                </Link>
                                {run.status === 'waiting' && run.resume_at && (
                                    <p className="text-xs text-muted-foreground">
                                        {t('Continues :when', {
                                            when: relativeTime(run.resume_at),
                                        })}
                                    </p>
                                )}
                                {run.error && (
                                    <p className="line-clamp-2 text-xs text-destructive">
                                        {run.error}
                                    </p>
                                )}
                                {(run.status === 'waiting' ||
                                    run.status === 'running') && (
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        className="h-6 text-xs"
                                        onClick={(event) => {
                                            event.stopPropagation();
                                            router.post(
                                                cancel.url([
                                                    workflowId,
                                                    run.id,
                                                ]),
                                                {},
                                                {
                                                    preserveScroll: true,
                                                    only: [
                                                        'runs',
                                                        'selectedRun',
                                                    ],
                                                },
                                            );
                                        }}
                                    >
                                        {t('Cancel run')}
                                    </Button>
                                )}
                            </div>
                        </li>
                    ))}
                </ol>
            )}
        </div>
    );
}
