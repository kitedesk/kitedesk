import { Head, Link, usePage } from '@inertiajs/react';
import { ArrowLeft, Home, RotateCw } from 'lucide-react';
import type { ReactNode } from 'react';
import AppLogoIcon from '@/components/app-logo-icon';
import { KiteDeskLogo, useShowsKiteDeskLogo } from '@/components/kitedesk-logo';
import { BrandLogo, useHasBrandLogo } from '@/components/brand-logo';
import { Button } from '@/components/ui/button';
import { NotFound } from '@/components/ui/not-found';
import { useTranslation } from '@/hooks/use-translation';
import AppLayout from '@/layouts/app-layout';
import { dashboard, home } from '@/routes';

/**
 * Shown for 403, 404, 429, 500 and 503 (see App\Domain\Support\ErrorPages). Signed-in people
 * keep their shell (sidebar or portal header) so they can carry on; guests and server errors
 * get a page of its own.
 */
export default function ErrorPage({
    status,
    title,
    description,
}: {
    status: number;
    title: string;
    description: string;
}) {
    const { t } = useTranslation();
    const { auth } = usePage().props;
    const signedIn = Boolean(auth.user);
    const retryable = status === 429 || status >= 500;

    const content = (
        <NotFound
            errorCode={String(status)}
            title={title}
            description={description}
        >
            <Button asChild>
                <Link href={signedIn ? dashboard() : home()}>
                    <Home />
                    {signedIn ? t('Go to dashboard') : t('Go to help center')}
                </Link>
            </Button>
            {retryable ? (
                <Button
                    variant="outline"
                    onClick={() => window.location.reload()}
                >
                    <RotateCw />
                    {t('Try again')}
                </Button>
            ) : (
                <Button variant="outline" onClick={() => window.history.back()}>
                    <ArrowLeft />
                    {t('Go back')}
                </Button>
            )}
        </NotFound>
    );

    return (
        <>
            <Head title={title} />
            {signedIn && status < 500 ? (
                <AppLayout>
                    <div className="flex flex-1 items-center justify-center p-6">
                        {content}
                    </div>
                </AppLayout>
            ) : (
                <Standalone>{content}</Standalone>
            )}
        </>
    );
}

function Standalone({ children }: { children: ReactNode }) {
    const { name } = usePage().props;
    const hasLogo = useHasBrandLogo();
    const showsKiteDeskLogo = useShowsKiteDeskLogo();

    return (
        <div className="flex min-h-svh flex-col bg-background">
            <header className="flex justify-center p-6">
                <Link
                    href={home()}
                    className="flex items-center gap-2 font-semibold"
                >
                    {hasLogo ? (
                        <BrandLogo className="h-8" />
                    ) : showsKiteDeskLogo ? (
                        <KiteDeskLogo className="h-10" />
                    ) : (
                        <>
                            <span className="flex size-8 items-center justify-center rounded-lg bg-primary text-primary-foreground">
                                <AppLogoIcon className="size-5 fill-current" />
                            </span>
                            {name}
                        </>
                    )}
                </Link>
            </header>
            <main className="flex flex-1 items-center justify-center p-6 pb-24">
                {children}
            </main>
        </div>
    );
}
