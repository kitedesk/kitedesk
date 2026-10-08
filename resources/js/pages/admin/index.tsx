import { Head, Link } from '@inertiajs/react';
import Heading from '@/components/heading';
import { useTranslation } from '@/hooks/use-translation';
import { useAdminNavSections } from '@/layouts/admin/admin-layout';

export default function AdminOverview() {
    const { t } = useTranslation();
    const sections = useAdminNavSections();

    return (
        <>
            <Head title={t('Admin center')} />
            <Heading
                title={t('Admin center')}
                description={t('Configure how your helpdesk works.')}
            />
            <div className="space-y-8">
                {sections.map((section) => {
                    const items = section.items.filter(
                        (item) => item.href !== '/admin',
                    );

                    return (
                        items.length > 0 && (
                            <section key={section.title} className="space-y-3">
                                <h2 className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                    {t(section.title)}
                                </h2>
                                <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                                    {items.map((item) => (
                                        <Link
                                            key={item.href}
                                            href={item.href}
                                            className="group flex gap-3 rounded-xl border bg-card p-4 shadow-xs transition-colors hover:border-primary/40"
                                        >
                                            <span className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                                                <item.icon className="size-4" />
                                            </span>
                                            <span>
                                                <span className="block text-sm font-medium group-hover:text-primary">
                                                    {t(item.title)}
                                                </span>
                                                <span className="text-xs text-muted-foreground">
                                                    {t(item.description)}
                                                </span>
                                            </span>
                                        </Link>
                                    ))}
                                </div>
                            </section>
                        )
                    );
                })}
            </div>
        </>
    );
}

AdminOverview.layout = {
    breadcrumbs: [{ title: 'Admin center', href: '/admin' }],
};
