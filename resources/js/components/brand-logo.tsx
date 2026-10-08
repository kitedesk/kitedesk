import { usePage } from '@inertiajs/react';
import { cn } from '@/lib/utils';

/**
 * The uploaded logo (and its dark-mode version), or nothing when the stock mark is used.
 */
export function BrandLogo({ className }: { className?: string }) {
    const { name, branding } = usePage().props;

    if (!branding?.logo) {
        return null;
    }

    const classes = cn('w-auto max-w-48 object-contain', className);

    return branding.logoDark ? (
        <>
            <img
                src={branding.logo}
                alt={name}
                className={cn(classes, 'dark:hidden')}
            />
            <img
                src={branding.logoDark}
                alt={name}
                className={cn(classes, 'hidden dark:block')}
            />
        </>
    ) : (
        <img src={branding.logo} alt={name} className={classes} />
    );
}

export function useHasBrandLogo(): boolean {
    return Boolean(usePage().props.branding?.logo);
}
