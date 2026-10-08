import { usePage } from '@inertiajs/react';
import AppLayoutTemplate from '@/layouts/app/app-sidebar-layout';
import PortalLayout from '@/layouts/portal/portal-layout';
import type { BreadcrumbItem } from '@/types';

/**
 * Staff get the agent workspace shell; customers get the portal shell.
 */
export default function AppLayout({
    breadcrumbs = [],
    children,
}: {
    breadcrumbs?: BreadcrumbItem[];
    children: React.ReactNode;
}) {
    const { auth } = usePage().props;

    if (!auth.isStaff) {
        return (
            <PortalLayout>
                <div className="mx-auto w-full max-w-6xl px-4 py-8">
                    {children}
                </div>
            </PortalLayout>
        );
    }

    return (
        <AppLayoutTemplate breadcrumbs={breadcrumbs}>
            {children}
        </AppLayoutTemplate>
    );
}
