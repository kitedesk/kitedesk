import { Link, usePage } from '@inertiajs/react';
import {
    AlarmClock,
    BarChart3,
    Bookmark,
    CheckCircle2,
    Clock3,
    Inbox,
    Layers,
    MessageSquareText,
    PlusCircle,
    Send,
    Settings2,
    UserRound,
    UsersRound,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarGroup,
    SidebarGroupLabel,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuBadge,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useEntitlements } from '@/hooks/use-entitlements';
import { usePermissions } from '@/hooks/use-permissions';
import { useTranslation } from '@/hooks/use-translation';
import { index as cannedResponses } from '@/routes/agent/canned-responses';
import { index as reports } from '@/routes/agent/reports';
import { index as requests } from '@/routes/agent/requests';
import { create, index } from '@/routes/agent/tickets';
import { statusColors } from '@/lib/tickets';
import { cn } from '@/lib/utils';
import type { AgentNavView } from '@/types';

const viewIcons: Record<string, LucideIcon> = {
    mine: UserRound,
    unassigned: Inbox,
    groups: UsersRound,
    all: Layers,
    sla: AlarmClock,
    pending: Clock3,
    solved: CheckCircle2,
};

export function AppSidebar() {
    const page = usePage();
    const { agentNav } = page.props;
    const { can, canAccessAdmin } = usePermissions();
    const { includes } = useEntitlements();
    const url = new URL(page.url, 'http://localhost');
    const onTicketList = url.pathname === index.url();
    const currentView = url.searchParams.get('view') ?? 'unassigned';
    const { t } = useTranslation();
    const views = agentNav?.views ?? [];
    const builtInViews = views.filter((view) => !view.saved && !view.status);
    const savedViews = views.filter((view) => view.saved);
    const statusViews = views.filter((view) => view.status);

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link
                                href={index()}
                                prefetch
                                cacheFor={['10s', '1m']}
                            >
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                    {can('tickets.create') && (
                        <SidebarMenuItem>
                            <SidebarMenuButton
                                asChild
                                tooltip={{ children: t('New ticket') }}
                                className="bg-primary text-primary-foreground shadow-sm hover:bg-primary/90 hover:text-primary-foreground active:bg-primary/90 active:text-primary-foreground"
                            >
                                <Link href={create()} prefetch>
                                    <PlusCircle />
                                    <span>{t('New ticket')}</span>
                                </Link>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                    )}
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <SidebarGroup className="px-2 py-0">
                    <SidebarGroupLabel>{t('Views')}</SidebarGroupLabel>
                    <SidebarMenu>
                        {builtInViews.map((view) => (
                            <ViewItem
                                key={view.key}
                                view={view}
                                icon={viewIcons[view.key] ?? Inbox}
                                isActive={
                                    onTicketList && currentView === view.key
                                }
                            />
                        ))}
                    </SidebarMenu>
                </SidebarGroup>
                {savedViews.length > 0 && (
                    <SidebarGroup className="px-2 py-0">
                        <SidebarGroupLabel>
                            {t('Saved views')}
                        </SidebarGroupLabel>
                        <SidebarMenu>
                            {savedViews.map((view) => (
                                <ViewItem
                                    key={view.key}
                                    view={view}
                                    icon={
                                        view.saved?.shared
                                            ? UsersRound
                                            : Bookmark
                                    }
                                    isActive={
                                        onTicketList && currentView === view.key
                                    }
                                />
                            ))}
                        </SidebarMenu>
                    </SidebarGroup>
                )}
                {statusViews.length > 0 && (
                    <SidebarGroup className="px-2 py-0">
                        <SidebarGroupLabel>{t('Statuses')}</SidebarGroupLabel>
                        <SidebarMenu>
                            {statusViews.map((view) => (
                                <ViewItem
                                    key={view.key}
                                    view={view}
                                    isActive={
                                        onTicketList && currentView === view.key
                                    }
                                />
                            ))}
                        </SidebarMenu>
                    </SidebarGroup>
                )}
                {agentNav && (
                    <SidebarGroup className="px-2 py-0">
                        <SidebarMenu>
                            {includes('internal_requests') && (
                                <SidebarMenuItem>
                                    <SidebarMenuButton
                                        asChild
                                        isActive={url.pathname.startsWith(
                                            requests.url(),
                                        )}
                                        tooltip={{ children: t('My requests') }}
                                    >
                                        <Link href={requests()} prefetch>
                                            <Send />
                                            <span>{t('My requests')}</span>
                                        </Link>
                                    </SidebarMenuButton>
                                </SidebarMenuItem>
                            )}
                            <SidebarMenuItem>
                                <SidebarMenuButton
                                    asChild
                                    isActive={url.pathname.startsWith(
                                        cannedResponses.url(),
                                    )}
                                    tooltip={{
                                        children: t('Canned responses'),
                                    }}
                                >
                                    <Link href={cannedResponses()} prefetch>
                                        <MessageSquareText />
                                        <span>{t('Canned responses')}</span>
                                    </Link>
                                </SidebarMenuButton>
                            </SidebarMenuItem>
                        </SidebarMenu>
                    </SidebarGroup>
                )}
                {can('reports.view') && includes('reports') && agentNav && (
                    <SidebarGroup className="px-2 py-0">
                        <SidebarMenu>
                            <SidebarMenuItem>
                                <SidebarMenuButton
                                    asChild
                                    isActive={url.pathname.startsWith(
                                        reports.url(),
                                    )}
                                    tooltip={{ children: t('Reports') }}
                                >
                                    <Link href={reports()} prefetch>
                                        <BarChart3 />
                                        <span>{t('Reports')}</span>
                                    </Link>
                                </SidebarMenuButton>
                            </SidebarMenuItem>
                        </SidebarMenu>
                    </SidebarGroup>
                )}
                {canAccessAdmin && (
                    <SidebarGroup className="mt-auto px-2 py-0">
                        <SidebarMenu>
                            <SidebarMenuItem>
                                <SidebarMenuButton
                                    asChild
                                    isActive={url.pathname.startsWith('/admin')}
                                    tooltip={{ children: t('Admin center') }}
                                >
                                    <Link href="/admin" prefetch>
                                        <Settings2 />
                                        <span>{t('Admin center')}</span>
                                    </Link>
                                </SidebarMenuButton>
                            </SidebarMenuItem>
                        </SidebarMenu>
                    </SidebarGroup>
                )}
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}

function ViewItem({
    view,
    icon: Icon,
    isActive,
}: {
    view: AgentNavView;
    /** Status queues show the status color instead. */
    icon?: LucideIcon;
    isActive: boolean;
}) {
    return (
        <SidebarMenuItem>
            <SidebarMenuButton
                asChild
                isActive={isActive}
                tooltip={{ children: view.label }}
            >
                <Link
                    href={index({ query: { view: view.key } })}
                    prefetch
                    cacheFor={['10s', '1m']}
                >
                    {Icon ? (
                        <Icon />
                    ) : (
                        // Icon-sized, so the color still shows when the sidebar is collapsed.
                        <span className="flex size-4 shrink-0 items-center justify-center">
                            <span
                                className={cn(
                                    'size-2.5 rounded-full',
                                    view.status &&
                                        statusColors[view.status.color].dot,
                                )}
                            />
                        </span>
                    )}
                    <span>{view.label}</span>
                </Link>
            </SidebarMenuButton>
            {view.count > 0 && (
                <SidebarMenuBadge
                    className={
                        view.key === 'sla'
                            ? 'text-red-600 dark:text-red-400'
                            : undefined
                    }
                >
                    {view.count}
                </SidebarMenuBadge>
            )}
        </SidebarMenuItem>
    );
}
