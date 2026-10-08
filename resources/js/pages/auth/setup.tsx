import { Form, Head } from '@inertiajs/react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useTranslation } from '@/hooks/use-translation';
import { store } from '@/routes/setup';

type Props = {
    code: string;
    helpdeskName: string;
    passwordRules: string;
};

/**
 * The first-run screen: names the helpdesk and creates the first administrator. The setup
 * code comes from the link `php artisan kitedesk:setup` prints, and is asked for otherwise.
 */
export default function Setup({ code, helpdeskName, passwordRules }: Props) {
    const { t } = useTranslation();

    return (
        <>
            <Head title={t('Set up your helpdesk')} />
            <Form
                {...store.form()}
                resetOnError={['password', 'password_confirmation']}
                disableWhileProcessing
                className="flex flex-col gap-6"
            >
                {({ processing, errors }) => (
                    <>
                        <input
                            type="hidden"
                            name="timezone"
                            value={
                                Intl.DateTimeFormat().resolvedOptions().timeZone
                            }
                        />

                        {code === '' || errors.code ? (
                            <div className="grid gap-2">
                                <Label htmlFor="code">{t('Setup code')}</Label>
                                <Input
                                    id="code"
                                    name="code"
                                    defaultValue={code}
                                    required
                                    autoFocus
                                    autoComplete="off"
                                    className="font-mono"
                                />
                                <p className="text-xs text-pretty text-muted-foreground">
                                    {t(
                                        'Run `php artisan kitedesk:setup` on the server to see it. With Docker, it is in the container logs.',
                                    )}
                                </p>
                                <InputError message={errors.code} />
                            </div>
                        ) : (
                            <input type="hidden" name="code" value={code} />
                        )}

                        <div className="grid gap-2">
                            <Label htmlFor="helpdesk_name">
                                {t('Helpdesk name')}
                            </Label>
                            <Input
                                id="helpdesk_name"
                                name="helpdesk_name"
                                defaultValue={helpdeskName}
                                required
                                maxLength={100}
                            />
                            <p className="text-xs text-pretty text-muted-foreground">
                                {t(
                                    'Shown to customers in the help center and emails. You can change it later in Branding.',
                                )}
                            </p>
                            <InputError message={errors.helpdesk_name} />
                        </div>

                        <fieldset className="grid gap-4 border-t pt-6">
                            <legend className="sr-only">
                                {t('Your administrator account')}
                            </legend>
                            <p className="text-sm font-medium">
                                {t('Your administrator account')}
                            </p>

                            <div className="grid gap-2">
                                <Label htmlFor="name">{t('Name')}</Label>
                                <Input
                                    id="name"
                                    name="name"
                                    autoComplete="name"
                                    required
                                    autoFocus={code !== ''}
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
                                    name="email"
                                    autoComplete="email"
                                    placeholder="email@example.com"
                                    required
                                />
                                <InputError message={errors.email} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="password">
                                    {t('Password')}
                                </Label>
                                <PasswordInput
                                    id="password"
                                    name="password"
                                    autoComplete="new-password"
                                    passwordrules={passwordRules}
                                    required
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
                                    required
                                />
                                <InputError
                                    message={errors.password_confirmation}
                                />
                            </div>
                        </fieldset>

                        <Button
                            type="submit"
                            size="lg"
                            className="w-full"
                            disabled={processing}
                        >
                            {processing && <Spinner />}
                            {t('Finish setup')}
                        </Button>
                    </>
                )}
            </Form>
        </>
    );
}

Setup.layout = {
    title: 'Set up your helpdesk',
    description:
        'Name your helpdesk and create the administrator account. This only happens once.',
};
