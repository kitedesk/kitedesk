import { Link, usePage } from '@inertiajs/react';
import {
    BarChart3,
    Bookmark,
    Inbox,
    MessageSquareText,
    Send,
    UsersRound,
} from 'lucide-react';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useAgentNav } from '@/hooks/use-agent-nav';
import { useEntitlements } from '@/hooks/use-entitlements';
import { usePermissions } from '@/hooks/use-permissions';
import { useTranslation } from '@/hooks/use-translation';
import { index as cannedResponses } from '@/routes/agent/canned-responses';
import { index as reports } from '@/routes/agent/reports';
import { index as requests } from '@/routes/agent/requests';
import { index } from '@/routes/agent/tickets';
import { ViewItem, viewIcons } from './ticket-views';

/**
 * The classic sidebar body: every view, saved view and status in a list, then the links and
 * the pages modules added, flat.
 */
export function LegacyNav() {
    const page = usePage();
    const { agentNav } = page.props;
    const { can } = usePermissions();
    const { includes } = useEntitlements();
    const { flat: moduleNav } = useAgentNav();
    const url = new URL(page.url, 'http://localhost');
    const onTicketList = url.pathname === index.url();
    const currentView = url.searchParams.get('view') ?? 'unassigned';
    const { t } = useTranslation();
    const views = agentNav?.views ?? [];
    const builtInViews = views.filter((view) => !view.saved && !view.status);
    const savedViews = views.filter((view) => view.saved);
    const statusViews = views.filter((view) => view.status);

    return (
        <>
            <SidebarGroup className="px-2 py-0">
                <SidebarGroupLabel>{t('Views')}</SidebarGroupLabel>
                <SidebarMenu>
                    {builtInViews.map((view) => (
                        <ViewItem
                            key={view.key}
                            view={view}
                            icon={viewIcons[view.key] ?? Inbox}
                            isActive={onTicketList && currentView === view.key}
                        />
                    ))}
                </SidebarMenu>
            </SidebarGroup>
            {savedViews.length > 0 && (
                <SidebarGroup className="px-2 py-0">
                    <SidebarGroupLabel>{t('Saved views')}</SidebarGroupLabel>
                    <SidebarMenu>
                        {savedViews.map((view) => (
                            <ViewItem
                                key={view.key}
                                view={view}
                                icon={
                                    view.saved?.shared ? UsersRound : Bookmark
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
            {agentNav && moduleNav.length > 0 && (
                <SidebarGroup className="px-2 py-0">
                    <SidebarMenu>
                        {moduleNav.map((item) => (
                            <SidebarMenuItem key={item.href}>
                                <SidebarMenuButton
                                    asChild
                                    isActive={url.pathname.startsWith(
                                        item.href,
                                    )}
                                    tooltip={{ children: t(item.title) }}
                                >
                                    <Link href={item.href} prefetch>
                                        <item.icon />
                                        <span>{t(item.title)}</span>
                                    </Link>
                                </SidebarMenuButton>
                            </SidebarMenuItem>
                        ))}
                    </SidebarMenu>
                </SidebarGroup>
            )}
        </>
    );
}
