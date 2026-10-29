import { Head, router, usePage } from '@inertiajs/react';
import AppearanceTabs from '@/components/appearance-tabs';
import Heading from '@/components/heading';
import { SidebarStyleChoice } from '@/components/sidebar-style-choice';
import { useTranslation } from '@/hooks/use-translation';
import { edit as editAppearance } from '@/routes/appearance';
import { update as updateSidebar } from '@/routes/sidebar-preference';

export default function Appearance() {
    const { t } = useTranslation();
    const { sidebar } = usePage().props;

    return (
        <>
            <Head title={t('Appearance settings')} />

            <h1 className="sr-only">{t('Appearance settings')}</h1>

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title={t('Appearance settings')}
                    description={t(
                        'Update the appearance settings for your account',
                    )}
                />
                <AppearanceTabs />
            </div>

            {sidebar && (
                <div className="mt-10 space-y-6">
                    <Heading
                        variant="small"
                        title={t('Sidebar')}
                        description={t(
                            'Choose how the sidebar is laid out for you.',
                        )}
                    />
                    <SidebarStyleChoice
                        value={sidebar.preference ?? 'default'}
                        onChange={(mode) =>
                            router.put(
                                updateSidebar.url(),
                                { mode },
                                { preserveScroll: true },
                            )
                        }
                        extra={{
                            value: 'default',
                            title: t('Follow the default'),
                            description:
                                sidebar.default === 'menus'
                                    ? t('Currently: menus and submenus.')
                                    : t('Currently: classic list.'),
                        }}
                    />
                </div>
            )}
        </>
    );
}

Appearance.layout = {
    breadcrumbs: [
        {
            title: 'Appearance settings',
            href: editAppearance(),
        },
    ],
};
