import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { MailCheck } from 'lucide-react';
import { Captcha } from '@/components/captcha';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTranslation } from '@/hooks/use-translation';
import { login } from '@/routes';
import { store } from '@/routes/guest/check';
import { create } from '@/routes/guest/tickets';

/**
 * "Check a request": people without an account ask for a fresh link to their request.
 */
export default function CheckRequest() {
    const { t } = useTranslation();
    const { guestTickets } = usePage().props;
    const form = useForm({
        email: '',
        ticket: '',
        'cf-turnstile-response': '',
    });

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        form.post(store.url(), {
            preserveScroll: true,
            onSuccess: () => form.reset('ticket'),
        });
    };

    return (
        <>
            <Head title={t('Check a request')} />

            <form
                onSubmit={submit}
                className="mx-auto w-full max-w-md space-y-6 px-4 py-10"
            >
                <div className="space-y-1">
                    <MailCheck className="mb-3 size-8 text-primary" />
                    <h1 className="text-2xl font-semibold tracking-tight">
                        {t('Check a request')}
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        {t(
                            "Enter your email and request number. We'll email you a link to see the conversation and reply.",
                        )}
                    </p>
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="email">{t('Email address')}</Label>
                    <Input
                        id="email"
                        type="email"
                        value={form.data.email}
                        onChange={(event) =>
                            form.setData('email', event.target.value)
                        }
                        autoComplete="email"
                        required
                        autoFocus
                    />
                    <InputError message={form.errors.email} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="ticket">{t('Request number')}</Label>
                    <Input
                        id="ticket"
                        value={form.data.ticket}
                        onChange={(event) =>
                            form.setData('ticket', event.target.value)
                        }
                        placeholder="#1234"
                        required
                    />
                    <InputError message={form.errors.ticket} />
                </div>

                <Captcha
                    onToken={(token) =>
                        form.setData('cf-turnstile-response', token)
                    }
                    resetKey={form.recentlySuccessful || form.errors}
                    error={form.errors['cf-turnstile-response']}
                />

                <Button
                    type="submit"
                    className="w-full"
                    disabled={form.processing}
                >
                    {t('Email me a link')}
                </Button>

                <p className="text-center text-sm text-muted-foreground">
                    <Link
                        href={login()}
                        className="text-primary hover:underline"
                    >
                        {t('Sign in')}
                    </Link>
                    {guestTickets && (
                        <>
                            {' · '}
                            <Link
                                href={create()}
                                className="text-primary hover:underline"
                            >
                                {t('Submit a new request')}
                            </Link>
                        </>
                    )}
                </p>
            </form>
        </>
    );
}
