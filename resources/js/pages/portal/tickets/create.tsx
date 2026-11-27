import { Head } from '@inertiajs/react';
import { RequestForm } from '@/components/tickets/request-form';
import { useTranslation } from '@/hooks/use-translation';
import { store } from '@/routes/portal/tickets';
import type { CategoryNode, FormFields, TicketField } from '@/types';

export default function NewRequest({
    categories,
    forms,
    globalFields,
    defaultFormId,
}: {
    categories: CategoryNode[];
    forms: FormFields;
    globalFields: TicketField[];
    defaultFormId: number | null;
}) {
    const { t } = useTranslation();

    return (
        <>
            <Head title={t('Submit a request')} />
            <RequestForm
                categories={categories}
                forms={forms}
                globalFields={globalFields}
                defaultFormId={defaultFormId}
                action={store.url()}
            />
        </>
    );
}
