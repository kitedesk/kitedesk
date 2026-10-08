import { Head, Link, router } from '@inertiajs/react';
import { Pencil, Search, Trash2, UserPlus } from 'lucide-react';
import { useRef, useState } from 'react';
import { AdminPageHeader } from '@/components/admin/page-header';
import { ConfirmAction } from '@/components/admin/confirm-action';
import { UserAvatar } from '@/components/tickets/user-avatar';
import { AnimatedTabs } from '@/components/ui/animated-tabs';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { SlidePagination } from '@/components/ui/slide-pagination';
import { useTranslation } from '@/hooks/use-translation';
import { relativeTime } from '@/lib/tickets';
import { create, destroy, edit, index, show } from '@/routes/admin/users';

type UserRow = {
    id: number;
    name: string;
    email: string;
    avatar: string | null;
    type: 'staff' | 'customer';
    role_label: string;
    organization: { id: number; name: string } | null;
    groups: { id: number; name: string }[];
    tickets_count: number;
    email_verified: boolean;
    invited: boolean;
    deactivated: boolean;
    last_login_at: string | null;
    created_at: string;
};

type Props = {
    audience: 'staff' | 'customers' | 'deactivated';
    search: string;
    deactivatedCount: number;
    users: {
        data: UserRow[];
        current_page: number;
        last_page: number;
        total: number;
    };
};

export default function UsersIndex({
    audience,
    search,
    users,
    deactivatedCount,
}: Props) {
    const { t } = useTranslation();
    const [query, setQuery] = useState(search);
    const timeout = useRef<number | undefined>(undefined);

    const visit = (params: Record<string, string | number>) =>
        router.get(
            index.url(),
            {
                ...(audience !== 'staff' ? { audience } : {}),
                ...(query ? { search: query } : {}),
                ...params,
            },
            { preserveState: true, replace: true },
        );

    return (
        <>
            <Head title={t('Users')} />
            <AdminPageHeader
                title={t('Users')}
                description={t(
                    'Invite agents, manage roles and group membership, and look up customers.',
                )}
                actions={
                    <Button asChild>
                        <Link
                            href={create({
                                query:
                                    audience === 'customers'
                                        ? { audience }
                                        : {},
                            })}
                        >
                            <UserPlus />{' '}
                            {audience === 'customers'
                                ? t('Add customer')
                                : t('Invite team member')}
                        </Link>
                    </Button>
                }
            />

            <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
                <AnimatedTabs
                    tabs={[
                        { id: 'staff', label: t('Team') },
                        { id: 'customers', label: t('Customers') },
                        ...(deactivatedCount > 0 || audience === 'deactivated'
                            ? [
                                  {
                                      id: 'deactivated',
                                      label: t('Deactivated'),
                                      badge: deactivatedCount,
                                  },
                              ]
                            : []),
                    ]}
                    activeTab={audience}
                    renderContent={false}
                    layoutId="users-audience"
                    variant="pill"
                    onChange={(id) =>
                        router.get(
                            index.url(),
                            id !== 'staff' ? { audience: id } : {},
                            { preserveState: false },
                        )
                    }
                />
                <div className="relative w-full sm:w-64">
                    <Search className="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground" />
                    <Input
                        value={query}
                        onChange={(event) => {
                            setQuery(event.target.value);
                            window.clearTimeout(timeout.current);
                            timeout.current = window.setTimeout(
                                () =>
                                    router.get(
                                        index.url(),
                                        {
                                            ...(audience !== 'staff'
                                                ? { audience }
                                                : {}),
                                            search: event.target.value,
                                        },
                                        { preserveState: true, replace: true },
                                    ),
                                300,
                            );
                        }}
                        placeholder={t('Search name or email…')}
                        className="pl-8"
                    />
                </div>
            </div>

            <div className="overflow-hidden rounded-xl border bg-card shadow-xs">
                <table className="w-full text-sm">
                    <thead>
                        <tr className="border-b bg-muted/40 text-left text-xs text-muted-foreground">
                            <th className="px-4 py-2.5 font-medium">
                                {t('Name')}
                            </th>
                            <th className="px-3 py-2.5 font-medium">
                                {audience !== 'customers'
                                    ? t('Role')
                                    : t('Organization')}
                            </th>
                            <th className="hidden px-3 py-2.5 font-medium md:table-cell">
                                {audience !== 'customers'
                                    ? t('Groups')
                                    : t('Requests')}
                            </th>
                            <th className="hidden px-3 py-2.5 font-medium sm:table-cell">
                                {t('Last sign-in')}
                            </th>
                            <th className="w-28 px-4 py-2.5" />
                        </tr>
                    </thead>
                    <tbody>
                        {users.data.map((user) => (
                            <tr
                                key={user.id}
                                className="border-b last:border-0"
                            >
                                <td className="px-4 py-3">
                                    <div className="flex items-center gap-3">
                                        <UserAvatar
                                            name={user.name}
                                            src={user.avatar}
                                        />
                                        <div className="min-w-0">
                                            <p className="flex items-center gap-2">
                                                <Link
                                                    href={show(user.id)}
                                                    prefetch
                                                    className="truncate font-medium hover:underline"
                                                >
                                                    {user.name}
                                                </Link>
                                                {user.deactivated ? (
                                                    <Badge variant="secondary">
                                                        {t('Deactivated')}
                                                    </Badge>
                                                ) : (
                                                    user.invited &&
                                                    user.type === 'staff' && (
                                                        <Badge variant="outline">
                                                            {t('Invited')}
                                                        </Badge>
                                                    )
                                                )}
                                            </p>
                                            <p className="truncate text-xs text-muted-foreground">
                                                {user.email}
                                            </p>
                                        </div>
                                    </div>
                                </td>
                                <td className="px-3 py-3">
                                    {audience !== 'customers' ? (
                                        <span className="rounded-md bg-muted px-1.5 py-0.5 text-xs font-medium">
                                            {user.role_label}
                                        </span>
                                    ) : (
                                        (user.organization?.name ?? (
                                            <span className="text-muted-foreground">
                                                —
                                            </span>
                                        ))
                                    )}
                                </td>
                                <td className="hidden px-3 py-3 md:table-cell">
                                    {audience !== 'customers' ? (
                                        <span className="text-muted-foreground">
                                            {user.groups
                                                .map((group) => group.name)
                                                .join(', ') || '—'}
                                        </span>
                                    ) : (
                                        user.tickets_count
                                    )}
                                </td>
                                <td className="hidden px-3 py-3 text-muted-foreground sm:table-cell">
                                    {user.last_login_at
                                        ? relativeTime(user.last_login_at)
                                        : t('Never')}
                                </td>
                                <td className="px-4 py-3">
                                    <div className="flex justify-end gap-1">
                                        <Button
                                            asChild
                                            variant="ghost"
                                            size="icon"
                                            title={t('Edit')}
                                        >
                                            <Link href={edit(user.id)}>
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
                                            title={t('Delete :name?', {
                                                name: user.name,
                                            })}
                                            description={t(
                                                'This cannot be undone. Tickets they requested are deleted too; tickets assigned to them become unassigned.',
                                            )}
                                            href={destroy.url(user.id)}
                                        />
                                    </div>
                                </td>
                            </tr>
                        ))}
                        {users.data.length === 0 && (
                            <tr>
                                <td
                                    colSpan={5}
                                    className="px-4 py-12 text-center text-muted-foreground"
                                >
                                    {t('No people found.')}
                                </td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>

            {users.last_page > 1 && (
                <div className="mt-4 flex justify-center">
                    <SlidePagination
                        pageCount={users.last_page}
                        page={users.current_page}
                        onChange={(page) => visit({ page })}
                    />
                </div>
            )}
        </>
    );
}

UsersIndex.layout = {
    breadcrumbs: [
        { title: 'Admin center', href: '/admin' },
        { title: 'Users', href: index() },
    ],
};
