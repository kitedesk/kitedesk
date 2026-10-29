import { Link } from '@inertiajs/react';
import {
    AlarmClock,
    CheckCircle2,
    Clock3,
    Inbox,
    Layers,
    UserRound,
    UsersRound,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import {
    SidebarMenuBadge,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { statusColors } from '@/lib/tickets';
import { cn } from '@/lib/utils';
import { index } from '@/routes/agent/tickets';
import type { AgentNavView } from '@/types';

export const viewIcons: Record<string, LucideIcon> = {
    mine: UserRound,
    unassigned: Inbox,
    groups: UsersRound,
    all: Layers,
    sla: AlarmClock,
    pending: Clock3,
    solved: CheckCircle2,
};

export function ViewItem({
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
