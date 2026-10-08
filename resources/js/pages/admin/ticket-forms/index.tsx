import { Head, Link } from '@inertiajs/react';
import { FileText, Pencil, Plus, Trash2 } from 'lucide-react';
import { ConfirmAction } from '@/components/admin/confirm-action';
import { FormsFieldsTabs } from '@/components/admin/forms-fields-tabs';
import { AdminPageHeader } from '@/components/admin/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useTranslation } from '@/hooks/use-translation';
import { create, destroy, edit, index } from '@/routes/admin/ticket-forms';

type FormSummary = {
    id: number;
    name: string;
    description: string;
    is_default: boolean;
    is_active: boolean;
    fields_count: number;
    categories_count: number;
};

export default function TicketFormsIndex({ forms }: { forms: FormSummary[] }) {
    const { t, tChoice } = useTranslation();

    return (
        <>
            <Head title={t('Ticket forms')} />
            <AdminPageHeader
                title={t('Forms & fields')}
                description={t(
                    'A form is a set of fields. Each category picks a form; tickets without one use the default form.',
                )}
                actions={
                    <Button asChild>
                        <Link href={create()}>
                            <Plus /> {t('New form')}
                        </Link>
                    </Button>
                }
            />
            <FormsFieldsTabs active="forms" />

            {forms.length === 0 ? (
                <div className="rounded-xl border border-dashed px-6 py-16 text-center text-sm text-muted-foreground">
                    <FileText className="mx-auto mb-2 size-6" />
                    {t('No forms yet.')}
                </div>
            ) : (
                <ul className="divide-y overflow-hidden rounded-xl border bg-card shadow-xs">
                    {forms.map((form) => (
                        <li
                            key={form.id}
                            className="flex items-center gap-3 px-4 py-3"
                        >
                            <div className="min-w-0 flex-1">
                                <p className="flex flex-wrap items-center gap-2 font-medium">
                                    <Link
                                        href={edit(form.id)}
                                        className="hover:underline"
                                    >
                                        {form.name}
                                    </Link>
                                    {form.is_default && (
                                        <Badge variant="secondary">
                                            {t('Default')}
                                        </Badge>
                                    )}
                                    {!form.is_active && (
                                        <Badge variant="outline">
                                            {t('Inactive')}
                                        </Badge>
                                    )}
                                </p>
                                <p className="truncate text-xs text-muted-foreground">
                                    {tChoice(
                                        ':count field|:count fields',
                                        form.fields_count,
                                    )}
                                    {' · '}
                                    {tChoice(
                                        'used by :count category|used by :count categories',
                                        form.categories_count,
                                    )}
                                    {form.description &&
                                        ` · ${form.description}`}
                                </p>
                            </div>
                            <Button
                                variant="ghost"
                                size="icon"
                                title={t('Edit')}
                                asChild
                            >
                                <Link href={edit(form.id)}>
                                    <Pencil />
                                </Link>
                            </Button>
                            <ConfirmAction
                                trigger={
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        title={t('Delete')}
                                    >
                                        <Trash2 />
                                    </Button>
                                }
                                title={t('Delete “:name”?', {
                                    name: form.name,
                                })}
                                description={t(
                                    'Categories that use this form switch to the default form. Values stored on tickets are kept.',
                                )}
                                href={destroy.url(form.id)}
                            />
                        </li>
                    ))}
                </ul>
            )}
        </>
    );
}

TicketFormsIndex.layout = {
    breadcrumbs: [
        { title: 'Admin center', href: '/admin' },
        { title: 'Forms & fields', href: index() },
    ],
};
