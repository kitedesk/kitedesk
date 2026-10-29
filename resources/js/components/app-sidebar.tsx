import { Link, usePage } from '@inertiajs/react';
import { PlusCircle, Settings2 } from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavUser } from '@/components/nav-user';
import { LegacyNav } from '@/components/sidebar/legacy-nav';
import { MenuNav } from '@/components/sidebar/menu-nav';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarGroup,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { usePermissions } from '@/hooks/use-permissions';
import { useTranslation } from '@/hooks/use-translation';
import { create, index } from '@/routes/agent/tickets';

export function AppSidebar() {
    const page = usePage();
    const { sidebar } = page.props;
    const { can, canAccessAdmin } = usePermissions();
    const { t } = useTranslation();
    const mode = sidebar?.mode ?? 'menus';
    const path = page.url.split('?')[0];

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
                {mode === 'legacy' ? <LegacyNav /> : <MenuNav />}
                {canAccessAdmin && (
                    <SidebarGroup className="mt-auto px-2 py-0">
                        <SidebarMenu>
                            <SidebarMenuItem>
                                <SidebarMenuButton
                                    asChild
                                    isActive={path.startsWith('/admin')}
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
