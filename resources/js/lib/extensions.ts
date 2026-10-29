import type { ResolvedComponent } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import type { ComponentType } from 'react';
import type { Feature } from '@/hooks/use-entitlements';
import type { NavItem, Permission } from '@/types';

/**
 * Hooks for first-party KiteDesk packages.
 * Only packages installed under `vendor/kitedesk/` are built into the app: their
 * `resources/js/index.ts` runs at startup (see app.tsx) and calls the register functions
 * below, and their pages render as `{package}::path`.
 */

type PageModule = { default: ResolvedComponent };

const corePages = import.meta.glob<PageModule>('../pages/**/*.tsx');
const packagePages = import.meta.glob<PageModule>(
    '/vendor/kitedesk/*/resources/js/pages/**/*.tsx',
);

/**
 * The page path without its package, e.g. "cloud::admin/billing" → "admin/billing", so
 * package pages get the same layout as core pages in the same area.
 */
export function pagePath(name: string): string {
    const separator = name.indexOf('::');

    return separator === -1 ? name : name.slice(separator + 2);
}

export async function resolvePage(name: string): Promise<ResolvedComponent> {
    const separator = name.indexOf('::');
    const load =
        separator === -1
            ? corePages[`../pages/${name}.tsx`]
            : packagePages[
                  `/vendor/kitedesk/${name.slice(0, separator)}/resources/js/pages/${pagePath(name)}.tsx`
              ];

    if (!load) {
        throw new Error(`Page not found: ${name}`);
    }

    return (await load()).default;
}

export type AdminNavItem = {
    title: string;
    href: string;
    icon: LucideIcon;
    description: string;
    /** Other pages that belong to this item (they highlight it too). */
    includes?: string[];
    /** The admin section permission that unlocks the page; shown to everyone when absent. */
    permission?: Permission;
    /** The plan feature the page belongs to; hidden when the plan leaves it out. */
    feature?: Feature;
    /** The module the page belongs to; hidden while an admin has it switched off. */
    module?: string;
};

const adminNavItems: { section: string; item: AdminNavItem }[] = [];
const settingsNavItems: { item: NavItem; staffOnly: boolean }[] = [];

/**
 * Adds an item to an admin center section, by its English title ("General", "People"…).
 * An unknown section is added after the core ones.
 */
export function registerAdminNav(section: string, item: AdminNavItem): void {
    adminNavItems.push({ section, item });
}

export function registeredAdminNav(): readonly {
    section: string;
    item: AdminNavItem;
}[] {
    return adminNavItems;
}

/**
 * Adds an item to the personal settings navigation.
 */
export function registerSettingsNav(
    item: NavItem,
    options: { staffOnly?: boolean } = {},
): void {
    settingsNavItems.push({ item, staffOnly: options.staffOnly ?? false });
}

export function registeredSettingsNav(): readonly {
    item: NavItem;
    staffOnly: boolean;
}[] {
    return settingsNavItems;
}

const appBanners: ComponentType[] = [];

/**
 * Adds a component rendered above every agent workspace page (e.g. a notice that support
 * is signed in). It decides from the page props whether to show anything.
 */
export function registerAppBanner(banner: ComponentType): void {
    appBanners.push(banner);
}

export function registeredAppBanners(): readonly ComponentType[] {
    return appBanners;
}

export type AgentNavItem = {
    title: string;
    href: string;
    icon: LucideIcon;
    /** The permission that unlocks the page; shown to every agent when absent. */
    permission?: Permission;
    /** The plan feature the page belongs to; hidden when the plan leaves it out. */
    feature?: Feature;
    /** The module the page belongs to; hidden while an admin has it switched off. */
    module?: string;
};

/** A page inside a menu. It inherits the group's module and feature unless it names its own. */
export type AgentNavChild = Omit<AgentNavItem, 'icon'> & {
    icon?: LucideIcon;
};

/**
 * A menu with submenus, e.g. one module's pages. In the "menus" sidebar it is a collapsible
 * menu; in the classic one its children are listed flat. The group disappears when none of
 * its children is visible.
 */
export type AgentNavGroup = {
    title: string;
    icon: LucideIcon;
    children: AgentNavChild[];
    permission?: Permission;
    feature?: Feature;
    module?: string;
};

export type AgentNavEntry = AgentNavItem | AgentNavGroup;

export function isAgentNavGroup(entry: AgentNavEntry): entry is AgentNavGroup {
    return 'children' in entry;
}

const agentNavItems: AgentNavEntry[] = [];

/**
 * Adds a page, or a menu with submenus, to the agent workspace sidebar, below the core links.
 * Titles are English keys, translated where they are rendered.
 */
export function registerAgentNav(item: AgentNavEntry): void {
    agentNavItems.push(item);
}

export function registeredAgentNav(): readonly AgentNavEntry[] {
    return agentNavItems;
}

/**
 * What a ticket panel receives: the ticket it is shown on.
 */
export type TicketPanelProps = {
    ticket: { id: number; subject?: string; organization_id?: number | null };
};

type TicketPanel = {
    panel: ComponentType<TicketPanelProps>;
    module?: string;
};

const ticketPanels: TicketPanel[] = [];

/**
 * Adds a panel to the side of the ticket page, below the ticket properties (e.g. a time
 * log). It fetches its own data, and is not rendered while its module is switched off.
 */
export function registerTicketPanel(
    panel: ComponentType<TicketPanelProps>,
    options: { module?: string } = {},
): void {
    ticketPanels.push({ panel, module: options.module });
}

export function registeredTicketPanels(): readonly TicketPanel[] {
    return ticketPanels;
}

export type PortalNavItem = {
    title: string;
    href: string;
    /** The module the page belongs to; hidden while an admin has it switched off. */
    module?: string;
    /** The path prefix that marks the item as current. Defaults to `href`. */
    match?: string;
    /** Extra check on the shared page props, e.g. that the customer has an organization. */
    visible?: (props: Record<string, unknown>) => boolean;
};

const portalNavItems: PortalNavItem[] = [];

/**
 * Adds a link to the customer portal's header, after "My requests". Titles are English
 * keys, translated where they are rendered. Only shown to signed-in customers.
 */
export function registerPortalNav(item: PortalNavItem): void {
    portalNavItems.push(item);
}

export function registeredPortalNav(): readonly PortalNavItem[] {
    return portalNavItems;
}
