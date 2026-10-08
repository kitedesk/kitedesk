import { usePage } from '@inertiajs/react';
import { useEcho } from '@laravel/echo-react';
import { useChannelName } from '@/hooks/use-channel-name';
import { CommandPalette } from '@/components/agent/command-palette';
import { AppContent } from '@/components/app-content';
import { AppShell } from '@/components/app-shell';
import { AppSidebar } from '@/components/app-sidebar';
import { AppSidebarHeader } from '@/components/app-sidebar-header';
import { useSocketIdHeader } from '@/hooks/use-socket-id-header';
import { registeredAppBanners } from '@/lib/extensions';
import { scheduleReload } from '@/lib/live-reload';
import type { AppLayoutProps } from '@/types';

/**
 * Keeps sidebar counts fresh when tickets change anywhere in the helpdesk, and tells the
 * server which socket this tab uses so its own changes aren't echoed back to it.
 */
function StaffActivityListener() {
    const channelName = useChannelName();
    useEcho(
        channelName('staff.tickets'),
        ['.ticket.created', '.ticket.updated', '.message.created'],
        () => scheduleReload(['agentNav']),
    );

    useSocketIdHeader();

    return null;
}

export default function AppSidebarLayout({
    children,
    breadcrumbs = [],
}: AppLayoutProps) {
    const { auth } = usePage().props;

    return (
        <AppShell variant="sidebar">
            <AppSidebar />
            <AppContent variant="sidebar" className="min-w-0 overflow-x-clip">
                {registeredAppBanners().map((Banner, index) => (
                    <Banner key={index} />
                ))}
                <AppSidebarHeader breadcrumbs={breadcrumbs} />
                {children}
            </AppContent>
            {auth.isStaff && (
                <>
                    <CommandPalette />
                    <StaffActivityListener />
                </>
            )}
        </AppShell>
    );
}
