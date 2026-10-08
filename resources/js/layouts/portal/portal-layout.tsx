import { Link, usePage } from '@inertiajs/react';
import { ExternalLink, LifeBuoy, Menu, PlusCircle } from 'lucide-react';
import type { ReactNode } from 'react';
import AppLogoIcon from '@/components/app-logo-icon';
import { BrandLogo } from '@/components/brand-logo';
import { UserAvatar } from '@/components/tickets/user-avatar';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { UserMenuContent } from '@/components/user-menu-content';
import { useNewRequestUrl } from '@/hooks/use-new-request-url';
import { useSocketIdHeader } from '@/hooks/use-socket-id-header';
import { useTranslation } from '@/hooks/use-translation';
import { cn } from '@/lib/utils';
import { login, register } from '@/routes';
import { index as agentTickets } from '@/routes/agent/tickets';
import { check as guestCheck } from '@/routes/guest';
import { index as helpIndex } from '@/routes/help';
import { index as portalTickets } from '@/routes/portal/tickets';

/**
 * Customer-facing shell: help center + "My requests".
 */
export default function PortalLayout({ children }: { children: ReactNode }) {
    const page = usePage();
    const { auth, name, branding } = page.props;
    const path = page.url.split('?')[0];
    const { t } = useTranslation();
    const newRequestUrl = useNewRequestUrl();

    useSocketIdHeader(Boolean(auth.user));

    const links = [
        {
            title: t('Help center'),
            href: helpIndex(),
            active: path.startsWith('/help'),
        },
        auth.user
            ? {
                  title: t('My requests'),
                  href: portalTickets(),
                  active: path.startsWith('/portal'),
              }
            : {
                  title: t('Check a request'),
                  href: guestCheck(),
                  active: path.startsWith('/requests/check'),
              },
    ];

    return (
        <div className="flex min-h-svh flex-col bg-background">
            {branding?.customCss && (
                // Admin-written CSS for the customer side, cleaned by Branding::cleanCss().
                <style
                    dangerouslySetInnerHTML={{ __html: branding.customCss }}
                />
            )}
            <header className="sticky top-0 z-30 border-b bg-background/80 backdrop-blur-md">
                <div className="mx-auto flex h-16 max-w-6xl items-center gap-6 px-4">
                    <Link
                        href={helpIndex()}
                        className="flex items-center gap-2 font-semibold"
                    >
                        {branding?.logo ? (
                            <BrandLogo className="h-8" />
                        ) : (
                            <>
                                <span className="flex size-8 items-center justify-center rounded-lg bg-primary text-primary-foreground">
                                    <AppLogoIcon className="size-5 fill-current" />
                                </span>
                                <span className="hidden sm:inline">{name}</span>
                            </>
                        )}
                    </Link>

                    <nav className="hidden items-center gap-1 md:flex">
                        {links.map((link) => (
                            <Link
                                key={link.title}
                                href={link.href}
                                prefetch
                                className={cn(
                                    'rounded-md px-3 py-1.5 text-sm text-muted-foreground transition-colors hover:bg-accent hover:text-foreground',
                                    link.active && 'bg-accent text-foreground',
                                )}
                            >
                                {link.title}
                            </Link>
                        ))}
                        {branding?.headerLinks.map((link) => (
                            <a
                                key={link.url}
                                href={link.url}
                                target="_blank"
                                rel="noopener noreferrer"
                                className="inline-flex items-center gap-1 rounded-md px-3 py-1.5 text-sm text-muted-foreground transition-colors hover:bg-accent hover:text-foreground"
                            >
                                {link.label}
                                <ExternalLink className="size-3" />
                            </a>
                        ))}
                    </nav>

                    <div className="ml-auto flex items-center gap-2">
                        {auth.isStaff && (
                            <Button
                                asChild
                                variant="outline"
                                size="sm"
                                className="hidden sm:inline-flex"
                            >
                                <Link href={agentTickets()}>
                                    <LifeBuoy /> {t('Agent workspace')}
                                </Link>
                            </Button>
                        )}
                        <Button
                            asChild
                            size="sm"
                            className="hidden sm:inline-flex"
                        >
                            <Link href={newRequestUrl}>
                                <PlusCircle /> {t('Submit a request')}
                            </Link>
                        </Button>

                        {auth.user ? (
                            <DropdownMenu>
                                <DropdownMenuTrigger className="rounded-full focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none">
                                    <UserAvatar
                                        name={auth.user.name}
                                        src={auth.user.avatar}
                                    />
                                </DropdownMenuTrigger>
                                <DropdownMenuContent
                                    className="w-56"
                                    align="end"
                                >
                                    <UserMenuContent user={auth.user} />
                                </DropdownMenuContent>
                            </DropdownMenu>
                        ) : (
                            <div className="flex items-center gap-1">
                                <Button asChild variant="ghost" size="sm">
                                    <Link href={login()}>{t('Sign in')}</Link>
                                </Button>
                                <Button
                                    asChild
                                    variant="outline"
                                    size="sm"
                                    className="hidden sm:inline-flex"
                                >
                                    <Link href={register()}>
                                        {t('Sign up')}
                                    </Link>
                                </Button>
                            </div>
                        )}

                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    className="md:hidden"
                                    aria-label={t('Menu')}
                                >
                                    <Menu />
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end">
                                {links.map((link) => (
                                    <DropdownMenuItem key={link.title} asChild>
                                        <Link href={link.href}>
                                            {link.title}
                                        </Link>
                                    </DropdownMenuItem>
                                ))}
                                {branding?.headerLinks.map((link) => (
                                    <DropdownMenuItem key={link.url} asChild>
                                        <a
                                            href={link.url}
                                            target="_blank"
                                            rel="noopener noreferrer"
                                        >
                                            {link.label}
                                        </a>
                                    </DropdownMenuItem>
                                ))}
                                <DropdownMenuItem asChild>
                                    <Link href={newRequestUrl}>
                                        {t('Submit a request')}
                                    </Link>
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </div>
                </div>
            </header>

            <main className="flex-1">{children}</main>

            <footer className="space-y-2 border-t py-8 text-center text-xs text-muted-foreground">
                {branding?.portalFooter && (
                    <p className="mx-auto max-w-2xl px-4 whitespace-pre-line">
                        {branding.portalFooter}
                    </p>
                )}
                <p>
                    © {new Date().getFullYear()} {name}
                    {branding?.showPoweredBy !== false && (
                        <>
                            {' · '}
                            {t('Powered by :product', { product: 'KiteDesk' })}
                        </>
                    )}
                </p>
            </footer>
        </div>
    );
}
