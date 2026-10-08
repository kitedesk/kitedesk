import { Link, router } from '@inertiajs/react';
import { Workflow } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { RunStatusBadge } from '@/components/workflows/run-status';
import { useTranslation } from '@/hooks/use-translation';
import { relativeTime } from '@/lib/tickets';
import { edit } from '@/routes/admin/workflows';
import { store } from '@/routes/agent/tickets/workflows';
import type { RunStatus } from '@/types';

export type TicketWorkflows = {
    manual: { id: number; name: string }[];
    runs: {
        id: number;
        workflow_id: number;
        workflow: string;
        status: RunStatus;
        status_label: string;
        created_at: string | null;
    }[];
};

/**
 * "Workflows" on the ticket: run a manual workflow, and see the workflows that ran here.
 */
export function WorkflowMenu({
    ticketId,
    workflows,
    canOpenWorkflows,
}: {
    ticketId: number;
    workflows: TicketWorkflows;
    canOpenWorkflows: boolean;
}) {
    const { t } = useTranslation();

    if (workflows.manual.length === 0 && workflows.runs.length === 0) {
        return null;
    }

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button variant="outline" size="sm">
                    <Workflow /> {t('Workflows')}
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-72">
                {workflows.manual.length > 0 && (
                    <>
                        <DropdownMenuLabel className="text-xs text-muted-foreground">
                            {t('Run workflow')}
                        </DropdownMenuLabel>
                        {workflows.manual.map((workflow) => (
                            <DropdownMenuItem
                                key={workflow.id}
                                onSelect={() =>
                                    router.post(
                                        store.url([ticketId, workflow.id]),
                                        {},
                                        { preserveScroll: true },
                                    )
                                }
                            >
                                {workflow.name}
                            </DropdownMenuItem>
                        ))}
                    </>
                )}
                {workflows.manual.length > 0 && workflows.runs.length > 0 && (
                    <DropdownMenuSeparator />
                )}
                {workflows.runs.length > 0 && (
                    <>
                        <DropdownMenuLabel className="text-xs text-muted-foreground">
                            {t('Recent runs')}
                        </DropdownMenuLabel>
                        {workflows.runs.map((run) => {
                            const content = (
                                <span className="flex w-full items-center gap-2">
                                    <span className="min-w-0 flex-1 truncate">
                                        {run.workflow}
                                    </span>
                                    <RunStatusBadge status={run.status} />
                                    <span className="text-[11px] text-muted-foreground">
                                        {relativeTime(run.created_at)}
                                    </span>
                                </span>
                            );

                            return canOpenWorkflows ? (
                                <DropdownMenuItem key={run.id} asChild>
                                    <Link
                                        href={edit(run.workflow_id, {
                                            query: { run: run.id },
                                        })}
                                    >
                                        {content}
                                    </Link>
                                </DropdownMenuItem>
                            ) : (
                                <DropdownMenuItem
                                    key={run.id}
                                    disabled
                                    className="opacity-100"
                                >
                                    {content}
                                </DropdownMenuItem>
                            );
                        })}
                    </>
                )}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
