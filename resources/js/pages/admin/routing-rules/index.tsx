import { Head, Link, router } from '@inertiajs/react';
import { ArrowDown, ArrowUp, Pencil, Plus, Route, Trash2 } from 'lucide-react';
import { ConfirmAction } from '@/components/admin/confirm-action';
import { AdminPageHeader } from '@/components/admin/page-header';
import { describeCondition } from '@/components/admin/routing-conditions';
import type {
    RoutingOptions,
    RoutingRule,
} from '@/components/admin/routing-conditions';
import { ActiveSwitch } from '@/components/sla/active-switch';
import { Button } from '@/components/ui/button';
import { useTranslation } from '@/hooks/use-translation';
import {
    create,
    destroy,
    edit,
    index,
    move,
    toggle,
} from '@/routes/admin/routing-rules';

export default function RoutingRulesIndex({
    rules,
    options,
}: {
    rules: RoutingRule[];
    options: RoutingOptions;
}) {
    const { t } = useTranslation();

    const outcome = (rule: RoutingRule) => {
        const parts = [
            t('assign to :group', {
                group:
                    options.groups.find(
                        (group) => group.id === rule.actions.group_id,
                    )?.name ?? `#${rule.actions.group_id}`,
            }),
        ];

        if (rule.actions.priority) {
            parts.push(
                t('set priority to :priority', {
                    priority:
                        options.priorities.find(
                            (priority) =>
                                priority.value === rule.actions.priority,
                        )?.label ?? rule.actions.priority,
                }),
            );
        }

        if (rule.actions.tags.length) {
            parts.push(t('tag :tags', { tags: rule.actions.tags.join(', ') }));
        }

        return parts.join(' · ');
    };

    return (
        <>
            <Head title={t('Routing rules')} />
            <AdminPageHeader
                title={t('Routing rules')}
                description={t(
                    'When a ticket is created without a group, or its category changes, rules are checked from top to bottom and the first match decides the group.',
                )}
                actions={
                    <Button asChild>
                        <Link href={create()}>
                            <Plus /> {t('New rule')}
                        </Link>
                    </Button>
                }
            />

            {rules.length === 0 ? (
                <div className="rounded-xl border border-dashed px-6 py-16 text-center text-sm text-muted-foreground">
                    <Route className="mx-auto mb-2 size-6" />
                    {t(
                        'No routing rules yet. New tickets stay without a group until an agent picks one.',
                    )}
                </div>
            ) : (
                <ol className="divide-y overflow-hidden rounded-xl border bg-card shadow-xs">
                    {rules.map((rule, position) => (
                        <li
                            key={rule.id}
                            className="flex items-center gap-3 px-4 py-3"
                        >
                            <div className="flex flex-col">
                                {(['up', 'down'] as const).map((direction) => (
                                    <button
                                        key={direction}
                                        type="button"
                                        aria-label={
                                            direction === 'up'
                                                ? t('Move up')
                                                : t('Move down')
                                        }
                                        disabled={
                                            direction === 'up'
                                                ? position === 0
                                                : position === rules.length - 1
                                        }
                                        onClick={() =>
                                            router.post(
                                                move.url(rule.id),
                                                { direction },
                                                { preserveScroll: true },
                                            )
                                        }
                                        className="rounded p-0.5 text-muted-foreground hover:bg-accent disabled:opacity-30"
                                    >
                                        {direction === 'up' ? (
                                            <ArrowUp className="size-3.5" />
                                        ) : (
                                            <ArrowDown className="size-3.5" />
                                        )}
                                    </button>
                                ))}
                            </div>
                            <span className="w-5 text-center text-xs text-muted-foreground tabular-nums">
                                {position + 1}
                            </span>
                            <div className="min-w-0 flex-1">
                                <Link
                                    href={edit(rule.id)}
                                    className="font-medium hover:underline"
                                >
                                    {rule.name}
                                </Link>
                                <p className="text-xs text-muted-foreground">
                                    {rule.conditions.length === 0
                                        ? t('Every ticket')
                                        : rule.conditions
                                              .map((condition) =>
                                                  describeCondition(
                                                      condition,
                                                      options,
                                                  ),
                                              )
                                              .join(
                                                  rule.match === 'all'
                                                      ? ` ${t('and')} `
                                                      : ` ${t('or')} `,
                                              )}
                                </p>
                                <p className="text-xs font-medium text-primary">
                                    → {outcome(rule)}
                                </p>
                            </div>
                            <ActiveSwitch
                                checked={rule.is_active}
                                label={t('Active')}
                                onChange={() =>
                                    router.patch(
                                        toggle.url(rule.id),
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
                                <Link href={edit(rule.id)}>
                                    <Pencil />
                                </Link>
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
                                    name: rule.name,
                                })}
                                description={t(
                                    'Tickets already routed keep their group.',
                                )}
                                href={destroy.url(rule.id)}
                            />
                        </li>
                    ))}
                </ol>
            )}
        </>
    );
}

RoutingRulesIndex.layout = {
    breadcrumbs: [
        { title: 'Admin center', href: '/admin' },
        { title: 'Routing rules', href: index() },
    ],
};
