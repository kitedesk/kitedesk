import { Head, Link } from '@inertiajs/react';
import { RequestForm } from '@/components/tickets/request-form';
import { useTranslation } from '@/hooks/use-translation';
import { login } from '@/routes';
import { check } from '@/routes/guest';
import { store } from '@/routes/guest/tickets';
import type { CategoryNode, FormFields } from '@/types';

export default function GuestNewRequest({
    categories,
    forms,
    defaultFormId,
}: {
    categories: CategoryNode[];
    forms: FormFields;
    defaultFormId: number | null;
}) {
    const { t } = useTranslation();

    return (
        <>
            <Head title={t('Submit a request')} />
            <RequestForm
                categories={categories}
                forms={forms}
                defaultFormId={defaultFormId}
                action={store.url()}
                guest
            />
            <p className="mx-auto max-w-2xl px-4 pb-10 text-sm text-muted-foreground">
                {t('Already have an account?')}{' '}
                <Link href={login()} className="text-primary hover:underline">
                    {t('Sign in')}
                </Link>
                {' · '}
                <Link href={check()} className="text-primary hover:underline">
                    {t('Check an existing request')}
                </Link>
            </p>
        </>
    );
}
