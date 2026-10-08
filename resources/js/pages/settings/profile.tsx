import { Form, Head, Link, router, usePage } from '@inertiajs/react';
import { Camera, Trash2 } from 'lucide-react';
import { useRef, useState } from 'react';
import ProfileAvatarController from '@/actions/App/Http/Controllers/Settings/ProfileAvatarController';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import DeleteUser from '@/components/delete-user';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { LocaleSelect, TimezoneSelect } from '@/components/preference-selects';
import { UserAvatar } from '@/components/tickets/user-avatar';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTranslation } from '@/hooks/use-translation';
import { edit } from '@/routes/profile';
import { send } from '@/routes/verification';
import type { Auth } from '@/types';

type PageProps = {
    auth: Auth;
};

type Props = {
    mustVerifyEmail: boolean;
    status?: string;
    profile: {
        job_title: string | null;
        phone: string | null;
        timezone: string | null;
        locale: string | null;
        signature: string | null;
    };
    locales: Record<string, string>;
    defaultLocale: string;
    timezones: string[];
};

export default function Profile({
    mustVerifyEmail,
    status,
    profile,
    locales,
    defaultLocale,
    timezones,
}: Props) {
    const { auth } = usePage<PageProps>().props;
    const { t } = useTranslation();
    const isStaff = auth.user.type === 'staff';

    return (
        <>
            <Head title={t('Profile settings')} />

            <h1 className="sr-only">{t('Profile settings')}</h1>

            <ProfilePhoto />

            <Form
                {...ProfileController.update.form()}
                options={{
                    preserveScroll: true,
                }}
                className="space-y-12"
            >
                {({ processing, errors }) => (
                    <>
                        <div className="space-y-6">
                            <Heading
                                variant="small"
                                title={t('Personal information')}
                                description={t(
                                    'How you appear to customers and your team',
                                )}
                            />

                            <div className="grid gap-2">
                                <Label htmlFor="name">{t('Name')}</Label>
                                <Input
                                    id="name"
                                    defaultValue={auth.user.name}
                                    name="name"
                                    required
                                    autoComplete="name"
                                    placeholder={t('Full name')}
                                />
                                <InputError message={errors.name} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="email">
                                    {t('Email address')}
                                </Label>
                                <Input
                                    id="email"
                                    type="email"
                                    defaultValue={auth.user.email}
                                    name="email"
                                    required
                                    autoComplete="username"
                                    placeholder={t('Email address')}
                                />
                                <InputError message={errors.email} />

                                {mustVerifyEmail &&
                                    auth.user.email_verified_at === null && (
                                        <div>
                                            <p className="text-sm text-muted-foreground">
                                                {t(
                                                    'Your email address is unverified.',
                                                )}{' '}
                                                <Link
                                                    href={send()}
                                                    as="button"
                                                    className="text-foreground underline decoration-neutral-300 underline-offset-4 transition-colors duration-300 ease-out hover:decoration-current! dark:decoration-neutral-500"
                                                >
                                                    {t(
                                                        'Click here to re-send the verification email.',
                                                    )}
                                                </Link>
                                            </p>

                                            {status ===
                                                'verification-link-sent' && (
                                                <div className="mt-2 text-sm font-medium text-green-600">
                                                    {t(
                                                        'A new verification link has been sent to your email address.',
                                                    )}
                                                </div>
                                            )}
                                        </div>
                                    )}
                            </div>

                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label htmlFor="job_title">
                                        {t('Job title')}
                                    </Label>
                                    <Input
                                        id="job_title"
                                        name="job_title"
                                        defaultValue={profile.job_title ?? ''}
                                        autoComplete="organization-title"
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
                                        defaultValue={profile.phone ?? ''}
                                        autoComplete="tel"
                                        placeholder={t('Optional')}
                                    />
                                    <InputError message={errors.phone} />
                                </div>
                            </div>
                        </div>

                        <div className="space-y-6">
                            <Heading
                                variant="small"
                                title={t('Language and region')}
                                description={t(
                                    'The language of the app and your emails, and the timezone for dates and times',
                                )}
                            />

                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label htmlFor="locale">
                                        {t('Language')}
                                    </Label>
                                    <LocaleSelect
                                        id="locale"
                                        locales={locales}
                                        defaultValue={profile.locale}
                                        emptyLabel={t('Default (:language)', {
                                            language: defaultLocale,
                                        })}
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
                                        defaultValue={profile.timezone}
                                        emptyLabel={t('This device’s timezone')}
                                    />
                                    <InputError message={errors.timezone} />
                                </div>
                            </div>
                        </div>

                        {isStaff && (
                            <div className="space-y-6">
                                <Heading
                                    variant="small"
                                    title={t('Signature')}
                                    description={t(
                                        'Added under your public replies. You can leave it out of any reply.',
                                    )}
                                />
                                <div className="grid gap-2">
                                    <Label
                                        htmlFor="signature"
                                        className="sr-only"
                                    >
                                        {t('Signature')}
                                    </Label>
                                    <textarea
                                        id="signature"
                                        name="signature"
                                        rows={4}
                                        maxLength={2000}
                                        defaultValue={profile.signature ?? ''}
                                        placeholder={t(
                                            'e.g. Ana Souza · Customer Success',
                                        )}
                                        className="rounded-md border bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30"
                                    />
                                    <InputError message={errors.signature} />
                                </div>
                            </div>
                        )}

                        <div className="flex items-center gap-4">
                            <Button
                                disabled={processing}
                                data-test="update-profile-button"
                            >
                                {t('Save')}
                            </Button>
                        </div>
                    </>
                )}
            </Form>

            <DeleteUser />
        </>
    );
}

function ProfilePhoto() {
    const { auth } = usePage<PageProps>().props;
    const { t } = useTranslation();
    const input = useRef<HTMLInputElement>(null);
    const [uploading, setUploading] = useState(false);
    const [error, setError] = useState<string | undefined>();

    const upload = (file: File) => {
        setError(undefined);
        router.post(
            ProfileAvatarController.update.url(),
            { avatar: file },
            {
                forceFormData: true,
                preserveScroll: true,
                onStart: () => setUploading(true),
                onFinish: () => setUploading(false),
                onError: (errors) => setError(errors.avatar),
            },
        );
    };

    return (
        <div className="space-y-6">
            <Heading
                variant="small"
                title={t('Photo')}
                description={t(
                    'Shown next to your name and messages. JPG, PNG or WebP, up to 2 MB.',
                )}
            />
            <div className="flex items-center gap-4">
                <UserAvatar
                    name={auth.user.name}
                    src={auth.user.avatar}
                    className="size-16 text-base [&_[data-slot=avatar-fallback]]:text-lg"
                />
                <div className="flex flex-wrap gap-2">
                    <input
                        ref={input}
                        type="file"
                        accept="image/jpeg,image/png,image/webp"
                        className="hidden"
                        onChange={(event) => {
                            const file = event.target.files?.[0];

                            if (file) {
                                upload(file);
                            }

                            event.target.value = '';
                        }}
                    />
                    <Button
                        type="button"
                        variant="outline"
                        disabled={uploading}
                        onClick={() => input.current?.click()}
                    >
                        <Camera />
                        {auth.user.avatar
                            ? t('Change photo')
                            : t('Upload photo')}
                    </Button>
                    {auth.user.avatar && (
                        <Button
                            type="button"
                            variant="ghost"
                            disabled={uploading}
                            onClick={() =>
                                router.delete(
                                    ProfileAvatarController.destroy.url(),
                                    { preserveScroll: true },
                                )
                            }
                        >
                            <Trash2 />
                            {t('Remove')}
                        </Button>
                    )}
                </div>
            </div>
            <InputError message={error} />
        </div>
    );
}

Profile.layout = {
    breadcrumbs: [
        {
            title: 'Profile settings',
            href: edit(),
        },
    ],
};
