import { usePage } from '@inertiajs/react';

import AppLogoIcon from '@/components/app-logo-icon';
import { BrandLogo, useHasBrandLogo } from '@/components/brand-logo';

export default function AppLogo() {
    const { name } = usePage().props;

    // Uploaded logos usually include the wordmark, so the name isn't repeated next to them.
    if (useHasBrandLogo()) {
        return <BrandLogo className="h-8" />;
    }

    return (
        <>
            <div className="flex aspect-square size-8 items-center justify-center rounded-md bg-sidebar-primary text-sidebar-primary-foreground">
                <AppLogoIcon className="size-5 fill-current" />
            </div>
            <div className="ml-1 grid flex-1 text-left text-sm">
                <span className="mb-0.5 truncate leading-tight font-semibold">
                    {name}
                </span>
            </div>
        </>
    );
}
