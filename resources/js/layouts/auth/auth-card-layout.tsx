import { Link } from '@inertiajs/react';
import type { PropsWithChildren } from 'react';
import AppLogoIcon from '@/components/app-logo-icon';
import { KiteDeskLogo, useShowsKiteDeskLogo } from '@/components/kitedesk-logo';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { useTranslation } from '@/hooks/use-translation';
import { home } from '@/routes';

export default function AuthCardLayout({
    children,
    title,
    description,
}: PropsWithChildren<{
    name?: string;
    title?: string;
    description?: string;
}>) {
    // Pages pass English titles; translate them here.
    const { t } = useTranslation();
    const showsKiteDeskLogo = useShowsKiteDeskLogo();

    return (
        <div className="flex min-h-svh flex-col items-center justify-center gap-6 bg-muted p-6 md:p-10">
            <div className="flex w-full max-w-md flex-col gap-6">
                <Link
                    href={home()}
                    className="flex items-center gap-2 self-center font-medium"
                >
                    {showsKiteDeskLogo ? (
                        <KiteDeskLogo variant="vertical" className="h-28" />
                    ) : (
                        <div className="flex h-9 w-9 items-center justify-center">
                            <AppLogoIcon className="size-9 fill-current text-black dark:text-white" />
                        </div>
                    )}
                </Link>

                <div className="flex flex-col gap-6">
                    <Card className="rounded-xl">
                        <CardHeader className="px-10 pt-8 pb-0 text-center">
                            <CardTitle className="text-xl">
                                {t(title ?? '')}
                            </CardTitle>
                            <CardDescription>
                                {t(description ?? '')}
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="px-10 py-8">
                            {children}
                        </CardContent>
                    </Card>
                </div>
            </div>
        </div>
    );
}
