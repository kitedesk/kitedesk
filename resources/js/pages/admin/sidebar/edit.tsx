import { Head, useForm } from '@inertiajs/react';
import { AdminPageHeader } from '@/components/admin/page-header';
import InputError from '@/components/input-error';
import { SidebarStyleChoice } from '@/components/sidebar-style-choice';
import { Button } from '@/components/ui/button';
import { useTranslation } from '@/hooks/use-translation';
import { edit, update } from '@/routes/admin/sidebar';

export default function SidebarSettings({ mode }: { mode: string }) {
    const { t } = useTranslation();
    const form = useForm({ mode });

    return (
        <>
            <Head title={t('Sidebar')} />
            <AdminPageHeader
                title={t('Sidebar')}
                description={t(
                    'How the agent sidebar is laid out by default. Agents can choose their own in their appearance settings.',
                )}
            />

            <form
                onSubmit={(event) => {
                    event.preventDefault();
                    form.put(update.url(), { preserveScroll: true });
                }}
                className="max-w-2xl space-y-6"
            >
                <SidebarStyleChoice
                    value={form.data.mode}
                    onChange={(value) => form.setData('mode', value)}
                />
                <InputError message={form.errors.mode} />
                <Button type="submit" disabled={form.processing}>
                    {t('Save')}
                </Button>
            </form>
        </>
    );
}

SidebarSettings.layout = {
    breadcrumbs: [
        { title: 'Admin center', href: '/admin' },
        { title: 'Sidebar', href: edit() },
    ],
};
