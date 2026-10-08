import { Head } from '@inertiajs/react';
import { RequestForm } from '@/components/tickets/request-form';
import { useTranslation } from '@/hooks/use-translation';
import { store } from '@/routes/portal/tickets';
import type { CategoryNode, FormFields } from '@/types';

export default function NewRequest({
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
            />
        </>
    );
}
