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
