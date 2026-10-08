import { Head, Link } from '@inertiajs/react';
import { KeyRound, Lock } from 'lucide-react';
import { SecretForm } from '@/components/secrets/secret-form';
import type { CustomerSecret } from '@/components/secrets/secret-form';
import { useTranslation } from '@/hooks/use-translation';

type Props = {
    secret: CustomerSecret;
    ticket: { number: string; subject: string; url: string };
    maxLength: number;
};

/**
 * Opened from a secret link, signed in as the requester or someone copied on the ticket.
 * Nothing is revealed or used up until the customer asks for it.
 */
export default function SecretPage({ secret, ticket, maxLength }: Props) {
    const { t } = useTranslation();

    return (
        <>
            <Head title={secret.label}>
                <meta name="robots" content="noindex, nofollow" />
            </Head>

            <div className="mx-auto w-full max-w-lg space-y-6 px-4 py-10">
                <div className="space-y-1">
                    <KeyRound className="mb-3 size-8 text-primary" />
                    <h1 className="text-2xl font-semibold tracking-tight">
                        {secret.label}
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        <Link href={ticket.url} className="hover:underline">
                            {t('Request :number: :subject', {
                                number: ticket.number,
                                subject: ticket.subject,
                            })}
                        </Link>
                    </p>
                </div>

                <div className="rounded-xl border bg-card p-5 shadow-xs">
                    <SecretForm secret={secret} maxLength={maxLength} />
                </div>

                <p className="flex items-start gap-2 text-xs text-muted-foreground">
                    <Lock className="mt-0.5 size-3.5 shrink-0" />
                    {t(
                        'Secrets are stored encrypted, never sent by email, and only shown to signed-in people on this request.',
                    )}
                </p>
            </div>
        </>
    );
}
