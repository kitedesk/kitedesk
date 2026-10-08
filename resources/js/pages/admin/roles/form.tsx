import { Head, Link, setLayoutProps, useForm } from '@inertiajs/react';
import { Lock } from 'lucide-react';
import { AdminPageHeader } from '@/components/admin/page-header';
import { TextField } from '@/components/admin/text-field';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { useTranslation } from '@/hooks/use-translation';
import { create, edit, index, store, update } from '@/routes/admin/roles';
import type { Permission, TicketAccess } from '@/types';

type Choice<T extends string> = {
    value: T;
    label: string;
    description: string;
};

type RoleData = {
    id: number;
    name: string;
    description: string | null;
    ticket_access: TicketAccess;
    permissions: Permission[];
    is_system: boolean;
    is_locked: boolean;
};

export default function RoleForm({
    role,
    permissionGroups,
    ticketAccessOptions,
}: {
    role: RoleData | null;
    permissionGroups: { title: string; permissions: Choice<Permission>[] }[];
    ticketAccessOptions: Choice<TicketAccess>[];
}) {
    const { t } = useTranslation();
    const form = useForm({
        name: role?.name ?? '',
        description: role?.description ?? '',
        ticket_access: role?.ticket_access ?? ('all' as TicketAccess),
        permissions: role?.permissions ?? ([] as Permission[]),
    });
    const errors = form.errors as Record<string, string | undefined>;
    const locked = role?.is_locked ?? false;
    const title = role ? role.name : t('New role');

    setLayoutProps({
        breadcrumbs: [
            { title: 'Admin center', href: '/admin' },
            { title: 'Roles', href: index() },
            role
                ? { title: role.name, href: edit(role.id) }
                : { title: 'New role', href: create() },
        ],
    });

    const toggle = (permission: Permission, granted: boolean) =>
        form.setData(
            'permissions',
            granted
                ? [...form.data.permissions, permission]
                : form.data.permissions.filter((p) => p !== permission),
        );

    const submit = () => {
        if (role) {
            form.put(update.url(role.id), { preserveScroll: true });
        } else {
            form.post(store.url(), { preserveScroll: true });
        }
    };

    return (
        <>
            <Head title={title} />
            <AdminPageHeader
                title={title}
                description={
                    locked
                        ? t(
                              'The administrator role always has every permission and sees every ticket. It cannot be changed.',
                          )
                        : t(
                              'Pick what members of this role may do. Internal notes are always available to the team.',
                          )
                }
            />

            <form
                onSubmit={(event) => {
                    event.preventDefault();
                    submit();
                }}
                className="max-w-3xl space-y-8"
            >
                <fieldset disabled={locked} className="space-y-8">
                    {!role?.is_system && (
                        <div className="grid gap-4 sm:grid-cols-2">
                            <TextField
                                id="name"
                                label={t('Name')}
                                value={form.data.name}
                                onChange={(value) =>
                                    form.setData('name', value)
                                }
                                error={errors.name}
                                placeholder={t('e.g. Tier 1 support')}
                                required
                            />
                            <TextField
                                id="description"
                                label={t('Description')}
                                value={form.data.description}
                                onChange={(value) =>
                                    form.setData('description', value)
                                }
                                error={errors.description}
                            />
                        </div>
                    )}

                    <section className="space-y-3">
                        <h2 className="text-sm font-medium">
                            {t('Which tickets they see')}
                        </h2>
                        <div
                            role="radiogroup"
                            className="grid gap-2 sm:grid-cols-3"
                        >
                            {ticketAccessOptions.map((option) => (
                                <label
                                    key={option.value}
                                    className="flex cursor-pointer flex-col gap-1 rounded-lg border p-3 text-sm has-[:checked]:border-primary has-[:checked]:bg-primary/5 has-[:disabled]:cursor-default"
                                >
                                    <span className="flex items-center gap-2 font-medium">
                                        <input
                                            type="radio"
                                            name="ticket_access"
                                            value={option.value}
                                            checked={
                                                form.data.ticket_access ===
                                                option.value
                                            }
                                            onChange={() =>
                                                form.setData(
                                                    'ticket_access',
                                                    option.value,
                                                )
                                            }
                                            className="size-4 accent-[var(--primary)]"
                                        />
                                        {option.label}
                                    </span>
                                    <span className="text-xs text-muted-foreground">
                                        {option.description}
                                    </span>
                                </label>
                            ))}
                        </div>
                        <InputError message={errors.ticket_access} />
                    </section>

                    {permissionGroups.map((group) => (
                        <section key={group.title} className="space-y-3">
                            <h2 className="text-sm font-medium">
                                {group.title}
                            </h2>
                            <div className="divide-y rounded-xl border bg-card">
                                {group.permissions.map((permission) => (
                                    <Label
                                        key={permission.value}
                                        className="flex items-start gap-3 px-4 py-3 font-normal"
                                    >
                                        <Checkbox
                                            checked={
                                                locked ||
                                                form.data.permissions.includes(
                                                    permission.value,
                                                )
                                            }
                                            onCheckedChange={(checked) =>
                                                toggle(
                                                    permission.value,
                                                    checked === true,
                                                )
                                            }
                                            className="mt-0.5"
                                        />
                                        <span className="grid gap-0.5">
                                            <span>{permission.label}</span>
                                            {permission.description && (
                                                <span className="text-xs text-muted-foreground">
                                                    {permission.description}
                                                </span>
                                            )}
                                        </span>
                                    </Label>
                                ))}
                            </div>
                        </section>
                    ))}
                    <InputError
                        message={
                            errors.permissions ??
                            Object.entries(errors).find(([key]) =>
                                key.startsWith('permissions.'),
                            )?.[1]
                        }
                    />
                </fieldset>

                <div className="flex gap-2">
                    {locked ? (
                        <p className="flex items-center gap-2 text-sm text-muted-foreground">
                            <Lock className="size-4" />
                            {t('Built-in and locked')}
                        </p>
                    ) : (
                        <Button type="submit" disabled={form.processing}>
                            {role ? t('Save changes') : t('Create role')}
                        </Button>
                    )}
                    <Button asChild variant="ghost">
                        <Link href={index()}>
                            {locked ? t('Back') : t('Cancel')}
                        </Link>
                    </Button>
                </div>
            </form>
        </>
    );
}
