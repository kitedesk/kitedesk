import { Head, Link, router } from '@inertiajs/react';
import {
    AlertTriangle,
    Copy,
    Pencil,
    Plus,
    Trash2,
    Workflow as WorkflowIcon,
} from 'lucide-react';
import { useState } from 'react';
import { ConfirmAction } from '@/components/admin/confirm-action';
import { AdminPageHeader } from '@/components/admin/page-header';
import { ActiveSwitch } from '@/components/sla/active-switch';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogTitle,
} from '@/components/ui/dialog';
import { useTranslation } from '@/hooks/use-translation';
import { relativeTime } from '@/lib/tickets';
import {
    create,
    destroy,
    duplicate,
    edit,
    index,
    toggle,
} from '@/routes/admin/workflows';
import type { WorkflowSummary, WorkflowTemplate } from '@/types';

export default function WorkflowsIndex({
    workflows,
    templates,
}: {
    workflows: WorkflowSummary[];
    templates: WorkflowTemplate[];
}) {
    const { t } = useTranslation();
    const [choosing, setChoosing] = useState(false);

    return (
        <>
            <Head title={t('Workflows')} />
            <AdminPageHeader
                title={t('Workflows')}
                description={t(
                    'Automate ticket handling: when something happens, check conditions and act — reply, assign, tag, notify, wait, loop or call other systems.',
                )}
                actions={
                    <Button onClick={() => setChoosing(true)}>
                        <Plus /> {t('New workflow')}
                    </Button>
                }
            />

            {workflows.length === 0 ? (
                <div className="rounded-xl border border-dashed px-6 py-16 text-center text-sm text-muted-foreground">
                    <WorkflowIcon className="mx-auto mb-2 size-6" />
                    {t(
                        'No workflows yet. Start from a template or a blank canvas.',
                    )}
                </div>
            ) : (
                <ul className="divide-y overflow-hidden rounded-xl border bg-card shadow-xs">
                    {workflows.map((workflow) => (
                        <li
                            key={workflow.id}
                            className="flex items-center gap-3 px-4 py-3"
                        >
                            <div className="min-w-0 flex-1">
                                <div className="flex flex-wrap items-center gap-2">
                                    <Link
                                        href={edit(workflow.id)}
                                        className="font-medium hover:underline"
                                    >
                                        {workflow.name}
                                    </Link>
                                    <span className="rounded-full bg-muted px-2 py-0.5 text-[11px] text-muted-foreground">
                                        {workflow.trigger_label}
                                    </span>
                                </div>
                                {workflow.description && (
                                    <p className="truncate text-xs text-muted-foreground">
                                        {workflow.description}
                                    </p>
                                )}
                                {workflow.disabled_reason && (
                                    <p className="flex items-center gap-1 text-xs text-destructive">
                                        <AlertTriangle className="size-3" />{' '}
                                        {workflow.disabled_reason}
                                    </p>
                                )}
                                <p className="text-xs text-muted-foreground">
                                    {t(':count step|:count steps', {
                                        count: workflow.steps,
                                    })}{' '}
                                    ·{' '}
                                    {t(
                                        ':count run this week|:count runs this week',
                                        { count: workflow.runs_last_week },
                                    )}
                                    {workflow.failures_last_week > 0 && (
                                        <span className="text-destructive">
                                            {' '}
                                            ·{' '}
                                            {t(':count failed', {
                                                count: workflow.failures_last_week,
                                            })}
                                        </span>
                                    )}
                                    {workflow.last_run_at && (
                                        <>
                                            {' '}
                                            ·{' '}
                                            {t('last run :when', {
                                                when: relativeTime(
                                                    workflow.last_run_at,
                                                ),
                                            })}
                                        </>
                                    )}
                                </p>
                            </div>
                            <ActiveSwitch
                                checked={workflow.is_active}
                                label={t('Active')}
                                onChange={() =>
                                    router.patch(
                                        toggle.url(workflow.id),
                                        {},
                                        { preserveScroll: true },
                                    )
                                }
                            />
                            <Button
                                variant="ghost"
                                size="icon"
                                title={t('Edit')}
                                asChild
                            >
                                <Link href={edit(workflow.id)}>
                                    <Pencil />
                                </Link>
                            </Button>
                            <Button
                                variant="ghost"
                                size="icon"
                                title={t('Duplicate')}
                                onClick={() =>
                                    router.post(duplicate.url(workflow.id))
                                }
                            >
                                <Copy />
                            </Button>
                            <ConfirmAction
                                trigger={
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        title={t('Delete')}
                                    >
                                        <Trash2 />
                                    </Button>
                                }
                                title={t('Delete “:name”?', {
                                    name: workflow.name,
                                })}
                                description={t(
                                    'Its run history is deleted too. Waiting runs stop.',
                                )}
                                href={destroy.url(workflow.id)}
                            />
                        </li>
                    ))}
                </ul>
            )}

            <Dialog open={choosing} onOpenChange={setChoosing}>
                <DialogContent className="sm:max-w-lg">
                    <DialogTitle>{t('New workflow')}</DialogTitle>
                    <DialogDescription>
                        {t(
                            'Start from a template and adjust it on the canvas.',
                        )}
                    </DialogDescription>
                    <div className="grid gap-2">
                        {templates.map((template) => (
                            <Link
                                key={template.key}
                                href={create({
                                    query: { template: template.key },
                                })}
                                className="rounded-lg border p-3 transition-colors hover:border-primary hover:bg-accent"
                            >
                                <p className="text-sm font-medium">
                                    {template.name}
                                </p>
                                <p className="text-xs text-muted-foreground">
                                    {template.description}
                                </p>
                            </Link>
                        ))}
                    </div>
                </DialogContent>
            </Dialog>
        </>
    );
}

WorkflowsIndex.layout = {
    breadcrumbs: [
        { title: 'Admin center', href: '/admin' },
        { title: 'Workflows', href: index() },
    ],
};
