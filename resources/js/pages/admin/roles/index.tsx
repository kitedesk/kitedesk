import { Head, Link } from '@inertiajs/react';
import { Lock, Pencil, Plus, ShieldCheck, Trash2 } from 'lucide-react';
import { ConfirmAction } from '@/components/admin/confirm-action';
import { AdminPageHeader } from '@/components/admin/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useEntitlements } from '@/hooks/use-entitlements';
import { useTranslation } from '@/hooks/use-translation';
import { create, destroy, edit, index } from '@/routes/admin/roles';

type RoleSummary = {
    id: number;
    name: string;
    description: string | null;
    ticket_access: string;
    is_system: boolean;
    is_locked: boolean;
    users_count: number;
    permissions_count: number;
};

export default function RolesIndex({
    roles,
    permissionsTotal,
}: {
    roles: RoleSummary[];
    permissionsTotal: number;
}) {
    const { t, tChoice } = useTranslation();
    const customRoles = useEntitlements().includes('custom_roles');

    return (
        <>
            <Head title={t('Roles')} />
            <AdminPageHeader
                title={t('Roles')}
                description={
                    customRoles
                        ? t(
                              'A role decides what team members may do and which tickets they see. Each team member has one role.',
                          )
                        : t(
                              'A role decides what team members may do and which tickets they see. Your plan includes the built-in roles.',
                          )
                }
                actions={
                    customRoles ? (
                        <Button asChild>
                            <Link href={create()}>
                                <Plus /> {t('New role')}
                            </Link>
                        </Button>
                    ) : undefined
                }
            />

            <ul className="divide-y overflow-hidden rounded-xl border bg-card shadow-xs">
                {roles.map((role) => (
                    <li
                        key={role.id}
                        className="flex items-center gap-3 px-4 py-3"
                    >
                        <span className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                            <ShieldCheck className="size-4" />
                        </span>
                        <div className="min-w-0 flex-1">
                            <p className="flex flex-wrap items-center gap-2 font-medium">
                                {role.is_system || customRoles ? (
                                    <Link
                                        href={edit(role.id)}
                                        className="hover:underline"
                                    >
                                        {role.name}
                                    </Link>
                                ) : (
                                    role.name
                                )}
                                {role.is_system && (
                                    <Badge variant="secondary">
                                        {t('Built-in')}
                                    </Badge>
                                )}
                            </p>
                            <p className="truncate text-xs text-muted-foreground">
                                {tChoice(
                                    ':count member|:count members',
                                    role.users_count,
                                )}
                                {' · '}
                                {t(':count of :total permissions', {
                                    count: role.permissions_count,
                                    total: permissionsTotal,
                                })}
                                {' · '}
                                {role.ticket_access}
                                {role.description && ` · ${role.description}`}
                            </p>
                        </div>
                        {(role.is_system || customRoles) && (
                            <Button
                                variant="ghost"
                                size="icon"
                                title={role.is_locked ? t('View') : t('Edit')}
                                asChild
                            >
                                <Link href={edit(role.id)}>
                                    {role.is_locked ? <Lock /> : <Pencil />}
                                </Link>
                            </Button>
                        )}
                        {!role.is_system && (
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
                                    name: role.name,
                                })}
                                description={t(
                                    'Only roles without team members can be deleted.',
                                )}
                                href={destroy.url(role.id)}
                            />
                        )}
                    </li>
                ))}
            </ul>
        </>
    );
}

RolesIndex.layout = {
    breadcrumbs: [
        { title: 'Admin center', href: '/admin' },
        { title: 'Roles', href: index() },
    ],
};
