import { Link, usePage } from '@inertiajs/react';
import type { PropsWithChildren } from 'react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { useEntitlements } from '@/hooks/use-entitlements';
import { useTranslation } from '@/hooks/use-translation';
import { registeredSettingsNav } from '@/lib/extensions';
import { cn, toUrl } from '@/lib/utils';
import { index as apiTokens } from '@/routes/api-tokens';
import { edit as editAppearance } from '@/routes/appearance';
import { index as connectedApps } from '@/routes/connected-apps';
import { edit } from '@/routes/profile';
import { edit as editSecurity } from '@/routes/security';
import type { NavItem } from '@/types';

const sidebarNavItems: NavItem[] = [
    {
        title: 'Profile',
        href: edit(),
        icon: null,
    },
    {
        title: 'Security',
        href: editSecurity(),
        icon: null,
    },
    {
        title: 'Appearance',
        href: editAppearance(),
        icon: null,
    },
];

/**
 * Staff only: AI apps connected over MCP, and REST API tokens when the plan includes the API.
 */
const staffNavItems: NavItem[] = [
    {
        title: 'Connected apps',
        href: connectedApps(),
        icon: null,
    },
];

export default function SettingsLayout({ children }: PropsWithChildren) {
    const { isCurrentOrParentUrl } = useCurrentUrl();
    const { t } = useTranslation();
    const { auth } = usePage().props;
    const { includes } = useEntitlements();
    const navItems = [
        ...sidebarNavItems,
        ...(auth.isStaff ? staffNavItems : []),
        ...(auth.isStaff && includes('api')
            ? [{ title: 'API tokens', href: apiTokens(), icon: null }]
            : []),
        ...registeredSettingsNav()
            .filter((entry) => auth.isStaff || !entry.staffOnly)
            .map((entry) => entry.item),
    ];

    return (
        <div className="px-4 py-6">
            <Heading
                title={t('Settings')}
                description={t('Manage your profile and account settings')}
            />

            <div className="flex flex-col lg:flex-row lg:space-x-12">
                <aside className="w-full max-w-xl lg:w-48">
                    <nav
                        className="flex flex-col space-y-1 space-x-0"
                        aria-label={t('Settings')}
                    >
                        {navItems.map((item, index) => (
                            <Button
                                key={`${toUrl(item.href)}-${index}`}
                                size="sm"
                                variant="ghost"
                                asChild
                                className={cn('w-full justify-start', {
                                    'bg-muted': isCurrentOrParentUrl(item.href),
                                })}
                            >
                                <Link href={item.href}>
                                    {item.icon && (
                                        <item.icon className="h-4 w-4" />
                                    )}
                                    {t(item.title)}
                                </Link>
                            </Button>
                        ))}
                    </nav>
                </aside>

                <Separator className="my-6 lg:hidden" />

                <div className="flex-1 md:max-w-2xl">
                    <section className="max-w-xl space-y-12">
                        {children}
                    </section>
                </div>
            </div>
        </div>
    );
}
