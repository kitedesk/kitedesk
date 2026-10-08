import { Form, Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import { AdminPageHeader } from '@/components/admin/page-header';
import { MultiSelectChips } from '@/components/admin/multi-select-chips';
import InputError from '@/components/input-error';
import { LocaleSelect, TimezoneSelect } from '@/components/preference-selects';
import { UserAvatar } from '@/components/tickets/user-avatar';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectLabel,
    SelectSeparator,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useTranslation } from '@/hooks/use-translation';
import { t } from '@/lib/i18n';
import { index, show, store, update } from '@/routes/admin/users';
import type { NamedRecord, Option } from '@/types';

type Props = {
    user: {
        id: number;
        name: string;
        email: string;
        avatar: string | null;
        role: string;
        organization_id: number | null;
        timezone: string | null;
        locale: string | null;
        job_title: string | null;
        phone: string | null;
        group_ids: number[];
    } | null;
    defaultRole: string;
    timezones: string[];
    locales: Record<string, string>;
    roles: (Option & { description: string | null })[];
    groups: NamedRecord[];
    organizations: NamedRecord[];
};

const NONE = '__none';

/** Role select value for customers; team roles use their id. */
const CUSTOMER = 'customer';

export default function UserForm({
    user,
    defaultRole,
    roles,
    groups,
    organizations,
    timezones,
    locales,
}: Props) {
    // Keeps the shared translator (`t`) in sync with the active locale.
    useTranslation();
    const isCustomer = (value: string) => value === CUSTOMER;
    const [role, setRole] = useState(user?.role ?? defaultRole);
    const [groupIds, setGroupIds] = useState<number[]>(user?.group_ids ?? []);
    const [organizationId, setOrganizationId] = useState(
        user?.organization_id ? String(user.organization_id) : NONE,
    );

    return (
        <>
            <Head
                title={
                    user ? t('Edit :name', { name: user.name }) : t('Invite')
                }
            />
            {user ? (
                <div className="mb-6 flex items-center gap-4">
                    <UserAvatar
                        name={user.name}
                        src={user.avatar}
                        className="size-12 [&_[data-slot=avatar-fallback]]:text-base"
                    />
                    <div className="min-w-0">
                        <h1 className="truncate text-xl font-semibold tracking-tight">
                            {t('Edit :name', { name: user.name })}
                        </h1>
                        <p className="truncate text-sm text-muted-foreground">
                            {user.email}
                        </p>
                    </div>
                </div>
            ) : (
                <AdminPageHeader
                    title={
                        isCustomer(role)
                            ? t('Add customer')
                            : t('Invite team member')
                    }
                    description={t(
                        'They will receive an email with a link to choose their password.',
                    )}
                />
            )}

            <Form
                {...(user ? update.form(user.id) : store.form())}
                transform={(data) => ({
                    ...data,
                    type: isCustomer(role) ? 'customer' : 'staff',
                    role_id: isCustomer(role) ? null : Number(role),
                    organization_id:
                        organizationId === NONE ? null : Number(organizationId),
                    group_ids: groupIds,
                })}
                className="max-w-2xl space-y-6"
            >
                {({ errors, processing }) => (
                    <>
                        <Card>
                            <CardHeader>
                                <CardTitle>{t('Profile')}</CardTitle>
                                <CardDescription>
                                    {t('Who they are and how to reach them.')}
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="grid gap-4 sm:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label htmlFor="name">{t('Name')}</Label>
                                    <Input
                                        id="name"
                                        name="name"
                                        defaultValue={user?.name}
                                        required
                                    />
                                    <InputError message={errors.name} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="email">{t('Email')}</Label>
                                    <Input
                                        id="email"
                                        name="email"
                                        type="email"
                                        defaultValue={user?.email}
                                        required
                                    />
                                    <InputError message={errors.email} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="job_title">
                                        {t('Job title')}
                                    </Label>
                                    <Input
                                        id="job_title"
                                        name="job_title"
                                        defaultValue={user?.job_title ?? ''}
                                        placeholder={t('Optional')}
                                    />
                                    <InputError message={errors.job_title} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="phone">{t('Phone')}</Label>
                                    <Input
                                        id="phone"
                                        name="phone"
                                        type="tel"
                                        defaultValue={user?.phone ?? ''}
                                        placeholder={t('Optional')}
                                    />
                                    <InputError message={errors.phone} />
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>{t('Access')}</CardTitle>
                                <CardDescription>
                                    {t(
                                        'Their role decides what they can see and do.',
                                    )}
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="grid gap-6">
                                <div className="grid gap-2">
                                    <Label>{t('Role')}</Label>
                                    <Select
                                        value={role}
                                        onValueChange={setRole}
                                    >
                                        <SelectTrigger className="w-full">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectGroup>
                                                <SelectLabel>
                                                    {t('Team member')}
                                                </SelectLabel>
                                                {roles.map((option) => (
                                                    <SelectItem
                                                        key={option.value}
                                                        value={option.value}
                                                    >
                                                        {option.label}
                                                    </SelectItem>
                                                ))}
                                            </SelectGroup>
                                            <SelectSeparator />
                                            <SelectItem value={CUSTOMER}>
                                                {t('Customer')}
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <p className="text-xs text-muted-foreground">
                                        {isCustomer(role)
                                            ? t(
                                                  'End user: submits and follows their own requests in the portal.',
                                              )
                                            : roles.find(
                                                  (option) =>
                                                      option.value === role,
                                              )?.description}
                                    </p>
                                    <InputError
                                        message={errors.role_id ?? errors.type}
                                    />
                                </div>

                                {isCustomer(role) ? (
                                    <div className="grid gap-2">
                                        <Label>{t('Organization')}</Label>
                                        <Select
                                            value={organizationId}
                                            onValueChange={setOrganizationId}
                                        >
                                            <SelectTrigger className="w-full">
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value={NONE}>
                                                    {t('None')}
                                                </SelectItem>
                                                {organizations.map(
                                                    (organization) => (
                                                        <SelectItem
                                                            key={
                                                                organization.id
                                                            }
                                                            value={String(
                                                                organization.id,
                                                            )}
                                                        >
                                                            {organization.name}
                                                        </SelectItem>
                                                    ),
                                                )}
                                            </SelectContent>
                                        </Select>
                                    </div>
                                ) : (
                                    <div className="grid gap-2">
                                        <Label>{t('Groups')}</Label>
                                        {groups.length ? (
                                            <MultiSelectChips
                                                options={groups.map(
                                                    (group) => ({
                                                        value: group.id,
                                                        label: group.name,
                                                    }),
                                                )}
                                                value={groupIds}
                                                onChange={setGroupIds}
                                            />
                                        ) : (
                                            <p className="text-sm text-muted-foreground">
                                                {t('No groups yet.')}
                                            </p>
                                        )}
                                    </div>
                                )}
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>
                                    {t('Language and region')}
                                </CardTitle>
                                <CardDescription>
                                    {t(
                                        'Used for their emails and dates. They can change these in their profile.',
                                    )}
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="grid gap-4 sm:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label htmlFor="locale">
                                        {t('Language')}
                                    </Label>
                                    <LocaleSelect
                                        id="locale"
                                        locales={locales}
                                        defaultValue={user?.locale ?? null}
                                        emptyLabel={t('Help desk default')}
                                    />
                                    <InputError message={errors.locale} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="timezone">
                                        {t('Timezone')}
                                    </Label>
                                    <TimezoneSelect
                                        id="timezone"
                                        timezones={timezones}
                                        defaultValue={user?.timezone ?? null}
                                        emptyLabel={t('Not set')}
                                    />
                                    <InputError message={errors.timezone} />
                                </div>
                            </CardContent>
                        </Card>

                        <div className="flex gap-2">
                            <Button type="submit" disabled={processing}>
                                {user
                                    ? t('Save changes')
                                    : t('Send invitation')}
                            </Button>
                            <Button asChild variant="ghost">
                                <Link href={user ? show(user.id) : index()}>
                                    {t('Cancel')}
                                </Link>
                            </Button>
                        </div>
                    </>
                )}
            </Form>
        </>
    );
}

UserForm.layout = {
    breadcrumbs: [
        { title: 'Admin center', href: '/admin' },
        { title: 'Users', href: index() },
    ],
};
