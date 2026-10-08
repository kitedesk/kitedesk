import { createInertiaApp, router } from '@inertiajs/react';
import { LazyMotion } from 'framer-motion';
import { Toaster } from '@/components/ui/sonner';
import { TooltipProvider } from '@/components/ui/tooltip';
import { initializeTheme } from '@/hooks/use-appearance';
import AdminLayout from '@/layouts/admin/admin-layout';
import AppLayout from '@/layouts/app-layout';
import AuthLayout from '@/layouts/auth-layout';
import { pagePath, resolvePage } from '@/lib/extensions';
import PortalLayout from '@/layouts/portal/portal-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { configureEcho } from '@laravel/echo-react';

// First-party KiteDesk packages register their navigation and other hooks here.
import.meta.glob('/vendor/kitedesk/*/resources/js/index.ts', { eager: true });

configureEcho({
    broadcaster: 'reverb',
});

// The brand name can change in the admin center, so it comes from the page rather than the build.
let appName =
    document
        .querySelector('meta[name="application-name"]')
        ?.getAttribute('content') || 'KiteDesk';

router.on('navigate', (event) => {
    const { name, branding } = event.detail.page.props;
    appName = name;

    const colors = document.getElementById('brand-colors');

    if (colors && branding && colors.textContent !== branding.stylesheet) {
        colors.textContent = branding.stylesheet;
    }
});

const loadMotionFeatures = () =>
    import('@/lib/motion-features').then((module) => module.default);

void createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    resolve: resolvePage,
    layout: (component) => {
        const name = pagePath(component);

        switch (true) {
            case name === 'welcome':
            case name === 'errors/show':
                // The error page picks its own shell from who is signed in.
                return null;
            case name.startsWith('auth/'):
                return AuthLayout;
            case name.startsWith('settings/'):
                return [AppLayout, SettingsLayout];
            case name === 'admin/workflows/editor':
                // The workflow editor needs the whole width for its canvas.
                return AppLayout;
            case name.startsWith('admin/'):
                return [AppLayout, AdminLayout];
            case name.startsWith('portal/') ||
                name.startsWith('help/') ||
                name.startsWith('guest/'):
                return PortalLayout;
            default:
                return AppLayout;
        }
    },
    strictMode: true,
    withApp(app) {
        return (
            <LazyMotion features={loadMotionFeatures}>
                <TooltipProvider delayDuration={0}>
                    {app}
                    <Toaster />
                </TooltipProvider>
            </LazyMotion>
        );
    },
    progress: {
        color:
            getComputedStyle(document.documentElement)
                .getPropertyValue('--primary')
                .trim() || '#4B5563',
    },
});

// This will set light / dark mode on load...
initializeTheme();
