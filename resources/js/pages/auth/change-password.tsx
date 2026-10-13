import { Form, Head } from '@inertiajs/react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useTranslation } from '@/hooks/use-translation';
import { logout } from '@/routes';
import { update } from '@/routes/password/change';

type Props = {
    reason: 'required' | 'expired';
    passwordRules: string;
};

export default function ChangePassword({ reason, passwordRules }: Props) {
    const { t } = useTranslation();

    return (
        <>
            <Head title={t('Choose a new password')} />

            <p className="mb-6 text-center text-sm text-muted-foreground">
                {reason === 'expired'
                    ? t('Your password expired. Choose a new one to continue.')
                    : t(
                          'An administrator asked you to choose a new password before you continue.',
                      )}
            </p>

            <Form
                {...update.form()}
                resetOnError={[
                    'current_password',
                    'password',
                    'password_confirmation',
                ]}
            >
                {({ processing, errors }) => (
                    <div className="grid gap-6">
                        <div className="grid gap-2">
                            <Label htmlFor="current_password">
                                {t('Current password')}
                            </Label>
                            <PasswordInput
                                id="current_password"
                                name="current_password"
                                autoComplete="current-password"
                                autoFocus
                            />
                            <InputError message={errors.current_password} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="password">
                                {t('New password')}
                            </Label>
                            <PasswordInput
                                id="password"
                                name="password"
                                autoComplete="new-password"
                                passwordrules={passwordRules}
                            />
                            <InputError message={errors.password} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="password_confirmation">
                                {t('Confirm password')}
                            </Label>
                            <PasswordInput
                                id="password_confirmation"
                                name="password_confirmation"
                                autoComplete="new-password"
                                passwordrules={passwordRules}
                            />
                            <InputError
                                message={errors.password_confirmation}
                            />
                        </div>

                        <Button
                            type="submit"
                            className="w-full"
                            disabled={processing}
                        >
                            {processing && <Spinner />}
                            {t('Save password')}
                        </Button>

                        <TextLink
                            href={logout()}
                            className="mx-auto block text-sm"
                        >
                            {t('Log out')}
                        </TextLink>
                    </div>
                )}
            </Form>
        </>
    );
}

ChangePassword.layout = {
    title: 'Choose a new password',
    description: 'You need a new password to keep using your account',
};
