import { Link, router, usePage } from '@inertiajs/react';
import {
    BarChart3,
    Bookmark,
    ChevronRight,
    Inbox,
    MessageSquareText,
    Send,
    Ticket,
    UsersRound,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useEffect, useState } from 'react';
import type { ReactNode } from 'react';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import {
    SidebarGroup,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarMenuSub,
    SidebarMenuSubButton,
    SidebarMenuSubItem,
    useSidebar,
} from '@/components/ui/sidebar';
import { useAgentNav } from '@/hooks/use-agent-nav';
import { useEntitlements } from '@/hooks/use-entitlements';
import { usePermissions } from '@/hooks/use-permissions';
import { useTranslation } from '@/hooks/use-translation';
import { isAgentNavGroup } from '@/lib/extensions';
import { statusColors } from '@/lib/tickets';
import { cn } from '@/lib/utils';
import { index as cannedResponses } from '@/routes/agent/canned-responses';
import { index as reports } from '@/routes/agent/reports';
import { index as requests } from '@/routes/agent/requests';
import { index } from '@/routes/agent/tickets';
import type { AgentNavView } from '@/types';
import { viewIcons } from './ticket-views';

/**
 * The sidebar body with menus and submenus: ticket views in one menu, one menu per module,
 * single links for the rest. A menu opens by itself when the current page is inside it, and
 * remembers whether you left it open.
 */
export function MenuNav() {
    const page = usePage();
    const { agentNav } = page.props;
    const { can } = usePermissions();
    const { includes } = useEntitlements();
    const { entries } = useAgentNav();
    const { t } = useTranslation();
    const url = new URL(page.url, 'http://localhost');
    const path = url.pathname;
    const onTicketList = path === index.url();
    const currentView = url.searchParams.get('view') ?? 'unassigned';
    const views = agentNav?.views ?? [];
    const builtInViews = views.filter((view) => !view.saved && !view.status);
    const savedViews = views.filter((view) => view.saved);
    const statusViews = views.filter((view) => view.status);
    const inTickets =
        path.startsWith(index.url()) && !path.startsWith('/agent/tickets/new');
    const queueTotal = builtInViews.find((view) => view.key === 'sla')?.count;

    const viewLink = (view: AgentNavView, icon?: LucideIcon) => (
        <SubLink
            key={view.key}
            href={index({ query: { view: view.key } }).url}
            active={onTicketList && currentView === view.key}
            icon={icon}
            dot={view.status ? statusColors[view.status.color].dot : undefined}
            count={view.count}
            urgent={view.key === 'sla'}
        >
            {view.label}
        </SubLink>
    );

    return (
        <SidebarGroup className="px-2 py-0">
            <SidebarMenu>
                {agentNav && (
                    <MenuGroup
                        id="tickets"
                        title={t('Tickets')}
                        icon={Ticket}
                        active={inTickets}
                        firstHref={index().url}
                        alert={queueTotal !== undefined && queueTotal > 0}
                    >
                        {builtInViews.map((view) =>
                            viewLink(view, viewIcons[view.key] ?? Inbox),
                        )}
                        {savedViews.length > 0 && (
                            <SubLabel>{t('Saved views')}</SubLabel>
                        )}
                        {savedViews.map((view) =>
                            viewLink(
                                view,
                                view.saved?.shared ? UsersRound : Bookmark,
                            ),
                        )}
                        {statusViews.length > 0 && (
                            <SubLabel>{t('Statuses')}</SubLabel>
                        )}
                        {statusViews.map((view) => viewLink(view))}
                    </MenuGroup>
                )}

                {agentNav && includes('internal_requests') && (
                    <SingleLink
                        href={requests.url()}
                        title={t('My requests')}
                        icon={Send}
                        active={path.startsWith(requests.url())}
                    />
                )}
                {agentNav && (
                    <SingleLink
                        href={cannedResponses.url()}
                        title={t('Canned responses')}
                        icon={MessageSquareText}
                        active={path.startsWith(cannedResponses.url())}
                    />
                )}
                {can('reports.view') && includes('reports') && agentNav && (
                    <SingleLink
                        href={reports.url()}
                        title={t('Reports')}
                        icon={BarChart3}
                        active={path.startsWith(reports.url())}
                    />
                )}

                {agentNav &&
                    entries.map((entry) =>
                        isAgentNavGroup(entry) ? (
                            <MenuGroup
                                key={entry.title}
                                id={`module:${entry.title}`}
                                title={t(entry.title)}
                                icon={entry.icon}
                                active={entry.children.some((child) =>
                                    path.startsWith(child.href),
                                )}
                                firstHref={entry.children[0].href}
                            >
                                {entry.children.map((child) => (
                                    <SubLink
                                        key={child.href}
                                        href={child.href}
                                        icon={child.icon}
                                        active={path.startsWith(child.href)}
                                    >
                                        {t(child.title)}
                                    </SubLink>
                                ))}
                            </MenuGroup>
                        ) : (
                            <SingleLink
                                key={entry.href}
                                href={entry.href}
                                title={t(entry.title)}
                                icon={entry.icon}
                                active={path.startsWith(entry.href)}
                            />
                        ),
                    )}
            </SidebarMenu>
        </SidebarGroup>
    );
}

function SingleLink({
    href,
    title,
    icon: Icon,
    active,
}: {
    href: string;
    title: string;
    icon: LucideIcon;
    active: boolean;
}) {
    return (
        <SidebarMenuItem>
            <SidebarMenuButton
                asChild
                isActive={active}
                tooltip={{ children: title }}
            >
                <Link href={href} prefetch>
                    <Icon />
                    <span>{title}</span>
                </Link>
            </SidebarMenuButton>
        </SidebarMenuItem>
    );
}

const storageKey = (id: string) => `sidebar:menu:${id}`;

function readOpen(id: string): boolean | null {
    try {
        const stored = window.localStorage.getItem(storageKey(id));

        return stored === null ? null : stored === '1';
    } catch {
        return null;
    }
}

function writeOpen(id: string, open: boolean): void {
    try {
        window.localStorage.setItem(storageKey(id), open ? '1' : '0');
    } catch {
        // Private windows and blocked storage just forget the choice.
    }
}

/**
 * A menu that opens and closes. Open when the current page is inside it, else as last left.
 * With the sidebar collapsed to icons there is no room for the submenu, so the icon opens
 * the menu's first page instead.
 */
function MenuGroup({
    id,
    title,
    icon: Icon,
    active,
    firstHref,
    alert = false,
    children,
}: {
    id: string;
    title: string;
    icon: LucideIcon;
    active: boolean;
    firstHref: string;
    /** Marks the menu when something inside needs attention while it is closed. */
    alert?: boolean;
    children: ReactNode;
}) {
    const { state, isMobile } = useSidebar();
    const [open, setOpen] = useState(() => active || (readOpen(id) ?? false));
    const iconOnly = state === 'collapsed' && !isMobile;

    useEffect(() => {
        if (active) {
            setOpen(true);
        }
    }, [active]);

    return (
        <Collapsible
            asChild
            open={open && !iconOnly}
            onOpenChange={(next) => {
                setOpen(next);
                writeOpen(id, next);
            }}
            className="group/collapsible"
        >
            <SidebarMenuItem>
                <CollapsibleTrigger asChild>
                    <SidebarMenuButton
                        tooltip={{ children: title }}
                        isActive={active && !open}
                        onClick={
                            iconOnly ? () => router.visit(firstHref) : undefined
                        }
                    >
                        <Icon />
                        <span>{title}</span>
                        {alert && !open && (
                            <span className="size-1.5 rounded-full bg-red-500" />
                        )}
                        <ChevronRight className="ml-auto transition-transform group-data-[state=open]/collapsible:rotate-90" />
                    </SidebarMenuButton>
                </CollapsibleTrigger>
                <CollapsibleContent>
                    <SidebarMenuSub>{children}</SidebarMenuSub>
                </CollapsibleContent>
            </SidebarMenuItem>
        </Collapsible>
    );
}

function SubLabel({ children }: { children: ReactNode }) {
    return (
        <li className="px-2 pt-2 pb-0.5 text-xs text-sidebar-foreground/60">
            {children}
        </li>
    );
}

function SubLink({
    href,
    active,
    icon: Icon,
    dot,
    count,
    urgent = false,
    children,
}: {
    href: string;
    active: boolean;
    icon?: LucideIcon;
    /** Tailwind class for a status color dot, instead of an icon. */
    dot?: string;
    count?: number;
    urgent?: boolean;
    children: ReactNode;
}) {
    return (
        <SidebarMenuSubItem>
            <SidebarMenuSubButton asChild isActive={active}>
                <Link href={href} prefetch>
                    {Icon && <Icon />}
                    {dot && (
                        <span className="flex size-4 shrink-0 items-center justify-center">
                            <span
                                className={cn('size-2.5 rounded-full', dot)}
                            />
                        </span>
                    )}
                    <span>{children}</span>
                    {count !== undefined && count > 0 && (
                        <span
                            className={cn(
                                'ml-auto text-xs text-sidebar-foreground/70 tabular-nums',
                                urgent && 'text-red-600 dark:text-red-400',
                            )}
                        >
                            {count}
                        </span>
                    )}
                </Link>
            </SidebarMenuSubButton>
        </SidebarMenuSubItem>
    );
}
