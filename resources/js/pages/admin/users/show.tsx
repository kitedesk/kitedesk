import {
    Head,
    Link,
    router,
    setLayoutProps,
    useForm,
    usePage,
} from '@inertiajs/react';
import {
    Activity as ActivityIcon,
    Briefcase,
    Building2,
    CalendarDays,
    CheckCircle2,
    CircleSlash,
    Clock,
    Fingerprint,
    KeyRound,
    Languages,
    LockKeyhole,
    LayoutDashboard,
    LogIn,
    LogOut,
    Mail,
    MailCheck,
    MoreHorizontal,
    Pencil,
    Phone,
    RotateCcw,
    Plug,
    ShieldCheck,
    ShieldOff,
    Trash2,
    UserCheck,
    UserX,
    Users,
    XCircle,
} from 'lucide-react';
import type { ReactNode } from 'react';
import { useState } from 'react';
import { SessionList } from '@/components/browser-sessions';
import type { BrowserSession } from '@/components/browser-sessions';
import { StatusBadge } from '@/components/tickets/status-badge';
import { UserAvatar } from '@/components/tickets/user-avatar';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { AnimatedNumber } from '@/components/ui/animated-number';
import { AnimatedTabs } from '@/components/ui/animated-tabs';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Label } from '@/components/ui/label';
import { Skeleton } from '@/components/ui/skeleton';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { useTranslation } from '@/hooks/use-translation';
import { formatDateTime, relativeTime } from '@/lib/tickets';
import { show as showTicket } from '@/routes/agent/tickets';
import {
    deactivate,
    destroy,
    edit,
    index,
    invitation,
    reactivate,
    show,
} from '@/routes/admin/users';
import { destroy as destroySessions } from '@/routes/admin/users/sessions';
import {
    requireChange as requirePasswordChange,
    update as updatePassword,
} from '@/routes/admin/users/password';
import { destroy as resetTwoFactor } from '@/routes/admin/users/two-factor';
import type { Ticket } from '@/types';

type UserDetail = {
    id: number;
    name: string;
    email: string;
    avatar: string | null;
    type: 'staff' | 'customer';
    role_label: string;
    is_admin: boolean;
    organization: { id: number; name: string } | null;
    groups: { id: number; name: string }[];
    job_title: string | null;
    phone: string | null;
    timezone: string | null;
    locale: string | null;
    is_available: boolean;
    email_verified: boolean;
    invited: boolean;
    last_login_at: string | null;
    deactivated_at: string | null;
    created_at: string;
    is_self: boolean;
};

type Stats = {
    requested: number;
    requested_open: number;
    requested_solved?: number;
    assigned_open?: number;
    solved_recently?: number;
};

type Security = {
    password_changed_at: string | null;
    password_change_required: boolean;
    password_expires_at: string | null;
    two_factor_enabled: boolean;
    passkeys: number;
    api_tokens: number;
    connected_apps: number;
    sessions: BrowserSession[];
};

type ActivityEntry = {
    id: number;
    event: string | null;
    description: string;
    causer: string | null;
    by_this_user: boolean;
    subject: { type: 'ticket' | 'user'; id: number; label: string } | null;
    changes: {
        old: Record<string, string | null>;
        new: Record<string, string | null>;
    };
    created_at: string;
};

type Props = {
    user: UserDetail;
    stats: Stats;
    tickets?: Ticket[];
    security?: Security;
    activity?: ActivityEntry[];
};

type Confirmation = {
    title: string;
    description: string;
    confirmLabel: string;
    href: string;
    method: 'post' | 'delete';
    destructive: boolean;
};

export default function UserShow({
    user,
    stats,
    tickets,
    security,
    activity,
}: Props) {
    const { t } = useTranslation();
    const { errors } = usePage().props;
    const [tab, setTab] = useState('overview');
    const [confirmation, setConfirmation] = useState<Confirmation | null>(null);
    const [settingPassword, setSettingPassword] = useState(false);
    const isStaff = user.type === 'staff';
    const isDeactivated = user.deactivated_at !== null;

    setLayoutProps({
        breadcrumbs: [
            { title: 'Admin center', href: '/admin' },
            { title: 'Users', href: index() },
            { title: user.name, href: show(user.id) },
        ],
    });

    return (
        <>
            <Head title={user.name} />

            <div className="mb-6 flex flex-wrap items-start justify-between gap-4">
                <div className="flex min-w-0 items-center gap-4">
                    <UserAvatar
                        name={user.name}
                        src={user.avatar}
                        className="size-14 [&_[data-slot=avatar-fallback]]:text-lg"
                    />
                    <div className="min-w-0">
                        <div className="flex flex-wrap items-center gap-2">
                            <h1 className="truncate text-xl font-semibold tracking-tight">
                                {user.name}
                            </h1>
                            <Badge variant={isStaff ? 'default' : 'secondary'}>
                                {user.role_label}
                            </Badge>
                            {isDeactivated && (
                                <Badge variant="destructive">
                                    {t('Deactivated')}
                                </Badge>
                            )}
                            {!isDeactivated && user.invited && (
                                <Badge variant="outline">
                                    {isStaff
                                        ? t('Invited')
                                        : t('Never signed in')}
                                </Badge>
                            )}
                            {!user.email_verified && (
                                <Badge variant="outline">
                                    {t('Unverified')}
                                </Badge>
                            )}
                            {isStaff &&
                                !isDeactivated &&
                                !user.is_available && (
                                    <Badge variant="outline">{t('Away')}</Badge>
                                )}
                        </div>
                        <p className="mt-0.5 truncate text-sm text-muted-foreground">
                            {[
                                user.job_title,
                                user.organization?.name,
                                user.email,
                            ]
                                .filter(Boolean)
                                .join(' · ')}
                        </p>
                    </div>
                </div>

                <div className="flex items-center gap-2">
                    <Button asChild>
                        <Link href={edit(user.id)}>
                            <Pencil /> {t('Edit')}
                        </Link>
                    </Button>
                    {!user.is_self && (
                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <Button
                                    variant="outline"
                                    size="icon"
                                    aria-label={t('More actions')}
                                >
                                    <MoreHorizontal />
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end" className="w-56">
                                {!isDeactivated && (
                                    <DropdownMenuItem
                                        onSelect={() =>
                                            router.post(
                                                invitation.url(user.id),
                                                {},
                                                { preserveScroll: true },
                                            )
                                        }
                                    >
                                        <MailCheck />
                                        {user.invited
                                            ? t('Resend invitation')
                                            : t('Send password reset link')}
                                    </DropdownMenuItem>
                                )}
                                {!isDeactivated && (
                                    <>
                                        <DropdownMenuItem
                                            onSelect={() =>
                                                setSettingPassword(true)
                                            }
                                        >
                                            <LockKeyhole /> {t('Set password')}
                                        </DropdownMenuItem>
                                        <DropdownMenuItem
                                            onSelect={() =>
                                                setConfirmation({
                                                    title: t(
                                                        'Ask :name for a new password?',
                                                        { name: user.name },
                                                    ),
                                                    description: t(
                                                        'The next time they use the help desk, they must choose a new password before doing anything else.',
                                                    ),
                                                    confirmLabel: t(
                                                        'Require new password',
                                                    ),
                                                    href: requirePasswordChange.url(
                                                        user.id,
                                                    ),
                                                    method: 'post',
                                                    destructive: false,
                                                })
                                            }
                                        >
                                            <RotateCcw />{' '}
                                            {t('Require new password')}
                                        </DropdownMenuItem>
                                    </>
                                )}
                                <DropdownMenuItem
                                    onSelect={() =>
                                        setConfirmation({
                                            title: t(
                                                'Sign :name out everywhere?',
                                                { name: user.name },
                                            ),
                                            description: t(
                                                'They are signed out of every browser and device, and need to sign in again.',
                                            ),
                                            confirmLabel: t(
                                                'Sign out everywhere',
                                            ),
                                            href: destroySessions.url(user.id),
                                            method: 'delete',
                                            destructive: false,
                                        })
                                    }
                                >
                                    <LogOut /> {t('Sign out everywhere')}
                                </DropdownMenuItem>
                                <DropdownMenuItem
                                    onSelect={() =>
                                        setConfirmation({
                                            title: t(
                                                'Reset two-factor authentication?',
                                            ),
                                            description: t(
                                                'Turns off two-factor authentication and removes the passkeys of :name, for when they lose their device. They sign in with their password and can set them up again.',
                                                { name: user.name },
                                            ),
                                            confirmLabel: t('Reset'),
                                            href: resetTwoFactor.url(user.id),
                                            method: 'delete',
                                            destructive: true,
                                        })
                                    }
                                >
                                    <ShieldOff /> {t('Reset two-factor')}
                                </DropdownMenuItem>
                                <DropdownMenuSeparator />
                                {isDeactivated ? (
                                    <DropdownMenuItem
                                        onSelect={() =>
                                            setConfirmation({
                                                title: t('Reactivate :name?', {
                                                    name: user.name,
                                                }),
                                                description: isStaff
                                                    ? t(
                                                          'They can sign in again and take a seat on your plan.',
                                                      )
                                                    : t(
                                                          'They can sign in and write in again.',
                                                      ),
                                                confirmLabel: t('Reactivate'),
                                                href: reactivate.url(user.id),
                                                method: 'post',
                                                destructive: false,
                                            })
                                        }
                                    >
                                        <UserCheck /> {t('Reactivate')}
                                    </DropdownMenuItem>
                                ) : (
                                    <DropdownMenuItem
                                        onSelect={() =>
                                            setConfirmation({
                                                title: t('Deactivate :name?', {
                                                    name: user.name,
                                                }),
                                                description: t(
                                                    'They are signed out and can’t sign in, use the API or open requests by email. Their tickets and history stay, and you can reactivate them anytime.',
                                                ),
                                                confirmLabel: t('Deactivate'),
                                                href: deactivate.url(user.id),
                                                method: 'post',
                                                destructive: true,
                                            })
                                        }
                                    >
                                        <UserX /> {t('Deactivate')}
                                    </DropdownMenuItem>
                                )}
                                <DropdownMenuItem
                                    variant="destructive"
                                    onSelect={() =>
                                        setConfirmation({
                                            title: t('Delete :name?', {
                                                name: user.name,
                                            }),
                                            description: t(
                                                'This cannot be undone. Tickets they requested are deleted too; tickets assigned to them become unassigned.',
                                            ),
                                            confirmLabel: t('Delete'),
                                            href: destroy.url(user.id),
                                            method: 'delete',
                                            destructive: true,
                                        })
                                    }
                                >
                                    <Trash2 /> {t('Delete')}
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>
                    )}
                </div>
            </div>

            {errors.user && (
                <Alert variant="destructive" className="mb-6">
                    <XCircle />
                    <AlertDescription>{errors.user}</AlertDescription>
                </Alert>
            )}

            {isDeactivated && (
                <Alert className="mb-6">
                    <CircleSlash />
                    <AlertTitle>
                        {t('Deactivated :time', {
                            time: relativeTime(user.deactivated_at),
                        })}
                    </AlertTitle>
                    <AlertDescription>
                        {t(
                            'They can’t sign in, use the API or open requests by email. Their tickets and history are kept.',
                        )}
                    </AlertDescription>
                </Alert>
            )}

            <div className="grid gap-6 lg:grid-cols-[20rem_minmax(0,1fr)]">
                <DetailsCard user={user} />

                <AnimatedTabs
                    activeTab={tab}
                    onChange={setTab}
                    layoutId="user-detail-tabs"
                    tabs={[
                        {
                            id: 'overview',
                            label: t('Overview'),
                            icon: <LayoutDashboard className="size-4" />,
                            content: (
                                <Overview
                                    user={user}
                                    stats={stats}
                                    tickets={tickets}
                                />
                            ),
                        },
                        {
                            id: 'security',
                            label: t('Security'),
                            icon: <ShieldCheck className="size-4" />,
                            content: (
                                <SecuritySummary
                                    user={user}
                                    security={security}
                                />
                            ),
                        },
                        {
                            id: 'activity',
                            label: t('Activity'),
                            icon: <ActivityIcon className="size-4" />,
                            content: (
                                <ActivityTimeline
                                    user={user}
                                    activity={activity}
                                />
                            ),
                        },
                    ]}
                />
            </div>

            <Dialog
                open={confirmation !== null}
                onOpenChange={(open) => !open && setConfirmation(null)}
            >
                <DialogContent>
                    <DialogTitle>{confirmation?.title}</DialogTitle>
                    <DialogDescription>
                        {confirmation?.description}
                    </DialogDescription>
                    <DialogFooter className="gap-2">
                        <DialogClose asChild>
                            <Button variant="secondary">{t('Cancel')}</Button>
                        </DialogClose>
                        <Button
                            variant={
                                confirmation?.destructive
                                    ? 'destructive'
                                    : 'default'
                            }
                            onClick={() => {
                                if (!confirmation) {
                                    return;
                                }

                                router.visit(confirmation.href, {
                                    method: confirmation.method,
                                    preserveScroll: true,
                                    onFinish: () => setConfirmation(null),
                                });
                            }}
                        >
                            {confirmation?.confirmLabel}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <SetPasswordDialog
                user={user}
                open={settingPassword}
                onClose={() => setSettingPassword(false)}
            />
        </>
    );
}

function SetPasswordDialog({
    user,
    open,
    onClose,
}: {
    user: UserDetail;
    open: boolean;
    onClose: () => void;
}) {
    const { t } = useTranslation();
    const form = useForm({
        password: '',
        password_confirmation: '',
        require_change: true,
        notify: false,
    });

    const close = () => {
        form.reset();
        form.clearErrors();
        onClose();
    };

    return (
        <Dialog open={open} onOpenChange={(value) => !value && close()}>
            <DialogContent>
                <form
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.put(updatePassword.url(user.id), {
                            preserveScroll: true,
                            onSuccess: close,
                        });
                    }}
                    className="space-y-5"
                >
                    <DialogTitle>
                        {t('Set a password for :name', { name: user.name })}
                    </DialogTitle>
                    <DialogDescription>
                        {t(
                            'Give them the password yourself, for example by phone. They are signed out everywhere.',
                        )}
                    </DialogDescription>

                    <div className="grid gap-2">
                        <Label htmlFor="new-user-password">
                            {t('New password')}
                        </Label>
                        <PasswordInput
                            id="new-user-password"
                            autoComplete="new-password"
                            value={form.data.password}
                            onChange={(event) =>
                                form.setData('password', event.target.value)
                            }
                        />
                        <InputError message={form.errors.password} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="new-user-password-confirmation">
                            {t('Confirm password')}
                        </Label>
                        <PasswordInput
                            id="new-user-password-confirmation"
                            autoComplete="new-password"
                            value={form.data.password_confirmation}
                            onChange={(event) =>
                                form.setData(
                                    'password_confirmation',
                                    event.target.value,
                                )
                            }
                        />
                    </div>

                    <div className="space-y-3">
                        <Label className="flex items-start gap-3 font-normal">
                            <Checkbox
                                checked={form.data.require_change}
                                onCheckedChange={(checked) =>
                                    form.setData(
                                        'require_change',
                                        checked === true,
                                    )
                                }
                                className="mt-0.5"
                            />
                            {t('Require a new password at next sign-in')}
                        </Label>
                        <Label className="flex items-start gap-3 font-normal">
                            <Checkbox
                                checked={form.data.notify}
                                onCheckedChange={(checked) =>
                                    form.setData('notify', checked === true)
                                }
                                className="mt-0.5"
                            />
                            {t(
                                'Email them that their password changed (without the password)',
                            )}
                        </Label>
                    </div>

                    <DialogFooter className="gap-2">
                        <Button
                            type="button"
                            variant="secondary"
                            onClick={close}
                        >
                            {t('Cancel')}
                        </Button>
                        <Button type="submit" disabled={form.processing}>
                            {t('Set password')}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function DetailsCard({ user }: { user: UserDetail }) {
    const { t, localeTag } = useTranslation();
    const localTime = user.timezone
        ? new Date().toLocaleTimeString(localeTag, {
              timeZone: user.timezone,
              hour: '2-digit',
              minute: '2-digit',
          })
        : null;

    return (
        <Card className="h-fit gap-0 py-0">
            <CardContent className="p-0">
                <dl className="divide-y text-sm">
                    <DetailRow icon={Mail} label={t('Email')}>
                        <a
                            href={`mailto:${user.email}`}
                            className="break-all hover:underline"
                        >
                            {user.email}
                        </a>
                    </DetailRow>
                    {user.phone && (
                        <DetailRow icon={Phone} label={t('Phone')}>
                            <a
                                href={`tel:${user.phone}`}
                                className="hover:underline"
                            >
                                {user.phone}
                            </a>
                        </DetailRow>
                    )}
                    {user.job_title && (
                        <DetailRow icon={Briefcase} label={t('Job title')}>
                            {user.job_title}
                        </DetailRow>
                    )}
                    {user.type === 'staff' ? (
                        <DetailRow icon={Users} label={t('Groups')}>
                            {user.groups.length > 0 ? (
                                <span className="flex flex-wrap gap-1">
                                    {user.groups.map((group) => (
                                        <Badge key={group.id} variant="outline">
                                            {group.name}
                                        </Badge>
                                    ))}
                                </span>
                            ) : (
                                <span className="text-muted-foreground">
                                    {t('No groups')}
                                </span>
                            )}
                        </DetailRow>
                    ) : (
                        <DetailRow icon={Building2} label={t('Organization')}>
                            {user.organization?.name ?? (
                                <span className="text-muted-foreground">
                                    {t('None')}
                                </span>
                            )}
                        </DetailRow>
                    )}
                    <DetailRow icon={Clock} label={t('Timezone')}>
                        {user.timezone && localTime ? (
                            <>
                                {t(':time local time', { time: localTime })}
                                <span className="block text-xs text-muted-foreground">
                                    {user.timezone.replaceAll('_', ' ')}
                                </span>
                            </>
                        ) : (
                            <span className="text-muted-foreground">
                                {t('Not set')}
                            </span>
                        )}
                    </DetailRow>
                    <DetailRow icon={Languages} label={t('Language')}>
                        {user.locale ?? (
                            <span className="text-muted-foreground">
                                {t('Help desk default')}
                            </span>
                        )}
                    </DetailRow>
                    <DetailRow icon={LogIn} label={t('Last sign-in')}>
                        {user.last_login_at ? (
                            <span title={formatDateTime(user.last_login_at)}>
                                {relativeTime(user.last_login_at)}
                            </span>
                        ) : (
                            <span className="text-muted-foreground">
                                {t('Never')}
                            </span>
                        )}
                    </DetailRow>
                    <DetailRow icon={CalendarDays} label={t('Joined')}>
                        {formatDateTime(user.created_at)}
                    </DetailRow>
                </dl>
            </CardContent>
        </Card>
    );
}

function DetailRow({
    icon: Icon,
    label,
    children,
}: {
    icon: typeof Mail;
    label: string;
    children: ReactNode;
}) {
    return (
        <div className="flex gap-3 px-4 py-3">
            <Icon className="mt-0.5 size-4 shrink-0 text-muted-foreground" />
            <div className="min-w-0 flex-1">
                <dt className="text-xs text-muted-foreground">{label}</dt>
                <dd className="mt-0.5">{children}</dd>
            </div>
        </div>
    );
}

function Overview({
    user,
    stats,
    tickets,
}: {
    user: UserDetail;
    stats: Stats;
    tickets?: Ticket[];
}) {
    const { t } = useTranslation();
    const tiles =
        user.type === 'staff'
            ? [
                  { label: t('Open, assigned'), value: stats.assigned_open },
                  {
                      label: t('Solved in 30 days'),
                      value: stats.solved_recently,
                  },
                  { label: t('Requested'), value: stats.requested },
              ]
            : [
                  { label: t('Requests'), value: stats.requested },
                  { label: t('Open'), value: stats.requested_open },
                  { label: t('Solved'), value: stats.requested_solved },
              ];

    return (
        <div className="space-y-6">
            <div className="grid grid-cols-3 gap-3">
                {tiles.map((tile) => (
                    <Card key={tile.label} className="gap-1 px-4 py-4">
                        <p className="text-xs text-muted-foreground">
                            {tile.label}
                        </p>
                        <AnimatedNumber
                            value={tile.value ?? 0}
                            className="text-2xl font-semibold tabular-nums"
                        />
                    </Card>
                ))}
            </div>

            <section>
                <h2 className="mb-2 text-sm font-medium">
                    {user.type === 'staff'
                        ? t('Recently assigned')
                        : t('Recent requests')}
                </h2>
                {tickets === undefined ? (
                    <div className="space-y-2">
                        <Skeleton className="h-12 w-full" />
                        <Skeleton className="h-12 w-full" />
                        <Skeleton className="h-12 w-full" />
                    </div>
                ) : tickets.length === 0 ? (
                    <p className="rounded-lg border border-dashed px-4 py-8 text-center text-sm text-muted-foreground">
                        {user.type === 'staff'
                            ? t('No tickets assigned yet.')
                            : t('No requests yet.')}
                    </p>
                ) : (
                    <ul className="divide-y rounded-lg border">
                        {tickets.map((ticket) => (
                            <li key={ticket.id}>
                                <Link
                                    href={showTicket(ticket.id)}
                                    className="flex items-center gap-3 px-4 py-3 text-sm hover:bg-accent/50"
                                >
                                    <StatusBadge
                                        status={ticket.status}
                                        customStatus={ticket.custom_status}
                                        className="shrink-0"
                                    />
                                    <span className="min-w-0 flex-1 truncate">
                                        <span className="text-muted-foreground">
                                            {ticket.number}
                                        </span>{' '}
                                        {ticket.subject}
                                    </span>
                                    <span className="shrink-0 text-xs text-muted-foreground">
                                        {relativeTime(ticket.updated_at)}
                                    </span>
                                </Link>
                            </li>
                        ))}
                    </ul>
                )}
            </section>
        </div>
    );
}

function SecuritySummary({
    user,
    security,
}: {
    user: UserDetail;
    security?: Security;
}) {
    const { t, tChoice } = useTranslation();

    if (security === undefined) {
        return (
            <div className="space-y-2">
                <Skeleton className="h-12 w-full" />
                <Skeleton className="h-12 w-full" />
                <Skeleton className="h-24 w-full" />
            </div>
        );
    }

    const passwordExpired =
        security.password_expires_at !== null &&
        new Date(security.password_expires_at) < new Date();

    const checks = [
        {
            icon: Mail,
            label: t('Email address'),
            ok: user.email_verified,
            value: user.email_verified ? t('Verified') : t('Not verified'),
        },
        {
            icon: LockKeyhole,
            label: t('Password'),
            ok:
                security.password_change_required || passwordExpired
                    ? false
                    : null,
            value: security.password_change_required
                ? t('New password required')
                : passwordExpired
                  ? t('Expired')
                  : security.password_changed_at
                    ? t('Changed :time', {
                          time: relativeTime(security.password_changed_at),
                      })
                    : t('Never set'),
        },
        {
            icon: ShieldCheck,
            label: t('Two-factor authentication'),
            ok: security.two_factor_enabled,
            value: security.two_factor_enabled ? t('On') : t('Off'),
        },
        {
            icon: Fingerprint,
            label: t('Passkeys'),
            ok: security.passkeys > 0,
            value:
                security.passkeys > 0
                    ? tChoice(
                          ':count passkey|:count passkeys',
                          security.passkeys,
                      )
                    : t('None'),
        },
        ...(user.type === 'staff'
            ? [
                  {
                      icon: KeyRound,
                      label: t('API tokens'),
                      ok: null,
                      value: String(security.api_tokens),
                  },
                  {
                      icon: Plug,
                      label: t('Connected apps'),
                      ok: null,
                      value: String(security.connected_apps),
                  },
              ]
            : []),
    ];

    return (
        <div className="space-y-6">
            <ul className="divide-y rounded-lg border">
                {checks.map((check) => (
                    <li
                        key={check.label}
                        className="flex items-center gap-3 px-4 py-3 text-sm"
                    >
                        <check.icon className="size-4 shrink-0 text-muted-foreground" />
                        <span className="flex-1">{check.label}</span>
                        <span className="flex items-center gap-1.5 text-muted-foreground">
                            {check.ok === true && (
                                <CheckCircle2 className="size-4 text-emerald-600 dark:text-emerald-400" />
                            )}
                            {check.ok === false && (
                                <XCircle className="size-4 text-muted-foreground/60" />
                            )}
                            {check.value}
                        </span>
                    </li>
                ))}
            </ul>

            <section className="space-y-2">
                <h2 className="text-sm font-medium">{t('Browser sessions')}</h2>
                <SessionList sessions={security.sessions} />
            </section>
        </div>
    );
}

const fieldLabels: Record<string, string> = {
    name: 'Name',
    email: 'Email',
    role: 'Role',
    organization: 'Organization',
    groups: 'Groups',
    job_title: 'Job title',
    phone: 'Phone',
    timezone: 'Timezone',
    locale: 'Language',
};

function ActivityTimeline({
    user,
    activity,
}: {
    user: UserDetail;
    activity?: ActivityEntry[];
}) {
    const { t } = useTranslation();

    if (activity === undefined) {
        return (
            <div className="space-y-2">
                <Skeleton className="h-10 w-full" />
                <Skeleton className="h-10 w-full" />
                <Skeleton className="h-10 w-full" />
            </div>
        );
    }

    if (activity.length === 0) {
        return (
            <p className="rounded-lg border border-dashed px-4 py-8 text-center text-sm text-muted-foreground">
                {t('No activity yet.')}
            </p>
        );
    }

    const describe = (entry: ActivityEntry): ReactNode => {
        if (entry.subject?.type === 'ticket') {
            const ticket = (
                <Link
                    href={showTicket(entry.subject.id)}
                    className="font-medium text-foreground hover:underline"
                >
                    {entry.subject.label}
                </Link>
            );

            return (
                <>
                    {entry.event === 'created'
                        ? t('created')
                        : entry.event === 'rated'
                          ? t('rated')
                          : t('updated')}{' '}
                    {ticket}
                </>
            );
        }

        if (entry.subject?.type === 'user') {
            return (
                <>
                    {t('changed the account of')}{' '}
                    <Link
                        href={show(entry.subject.id)}
                        className="font-medium text-foreground hover:underline"
                    >
                        {entry.subject.label}
                    </Link>
                </>
            );
        }

        switch (entry.event) {
            case 'created':
                return t('created this account');
            case 'updated':
                return t('updated this account');
            case 'deactivated':
                return t('deactivated this account');
            case 'reactivated':
                return t('reactivated this account');
            case 'sessions_revoked':
                return t('signed this person out everywhere');
            case 'two_factor_reset':
                return t('reset two-factor authentication');
            case 'password_set':
                return t('set a new password');
            case 'password_change_required':
                return t('asked for a new password');
            case 'password_changed':
                return t('changed their password');
            case 'password_reset':
                return t('reset their password by email');
            case 'login':
                return t('signed in');
            case 'logout':
                return t('signed out');
            case 'login_failed':
                return t('failed to sign in');
            case 'two_factor_enabled':
                return t('turned on two-factor authentication');
            case 'two_factor_disabled':
                return t('turned off two-factor authentication');
            case 'support_access':
                return t('signed in as this person (support access)');
            default:
                return entry.description;
        }
    };

    return (
        <ol className="relative ml-1 space-y-4 border-l pl-5">
            {activity.map((entry) => (
                <li key={entry.id} className="text-sm">
                    <span
                        className={
                            entry.by_this_user
                                ? 'absolute -left-[4.5px] mt-1.5 size-2 rounded-full bg-primary'
                                : 'absolute -left-[4.5px] mt-1.5 size-2 rounded-full bg-border'
                        }
                    />
                    <p className="text-muted-foreground">
                        <span className="font-medium text-foreground">
                            {entry.by_this_user
                                ? user.name
                                : (entry.causer ?? t('System'))}
                        </span>{' '}
                        {describe(entry)}
                        <span
                            className="ml-1 text-xs"
                            title={formatDateTime(entry.created_at)}
                        >
                            · {relativeTime(entry.created_at)}
                        </span>
                    </p>
                    {entry.event === 'updated' &&
                        entry.subject === null &&
                        Object.keys(entry.changes.new).length > 0 && (
                            <ul className="mt-1 space-y-0.5 text-xs">
                                {Object.entries(entry.changes.new).map(
                                    ([field, value]) => (
                                        <li key={field}>
                                            {fieldLabels[field]
                                                ? t(fieldLabels[field])
                                                : field}
                                            :{' '}
                                            <span className="text-muted-foreground line-through">
                                                {entry.changes.old[field] ??
                                                    '—'}
                                            </span>{' '}
                                            →{' '}
                                            <span className="font-medium">
                                                {value ?? '—'}
                                            </span>
                                        </li>
                                    ),
                                )}
                            </ul>
                        )}
                </li>
            ))}
        </ol>
    );
}
