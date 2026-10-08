import { usePage } from '@inertiajs/react';
import type { CSSProperties } from 'react';
import { cn } from '@/lib/utils';

const LOGOS = {
    horizontal: {
        src: '/images/brand/logo-horizontal.png',
        ratio: '960 / 347',
    },
    vertical: { src: '/images/brand/logo-vertical.png', ratio: '640 / 617' },
};

/**
 * The KiteDesk wordmark, in brand navy (white in dark mode). Size it by height.
 */
export function KiteDeskLogo({
    variant = 'horizontal',
    className,
}: {
    variant?: keyof typeof LOGOS;
    className?: string;
}) {
    const logo = LOGOS[variant];
    const style: CSSProperties = {
        aspectRatio: logo.ratio,
        maskImage: `url(${logo.src})`,
        WebkitMaskImage: `url(${logo.src})`,
        maskSize: 'contain',
        WebkitMaskSize: 'contain',
        maskRepeat: 'no-repeat',
        WebkitMaskRepeat: 'no-repeat',
    };

    return (
        <span
            role="img"
            aria-label="KiteDesk"
            style={style}
            className={cn(
                'inline-block shrink-0 bg-current text-[#142948] dark:text-white',
                className,
            )}
        />
    );
}

/**
 * Whether to show the KiteDesk wordmark: no logo was uploaded and the name is still
 * KiteDesk, so the wordmark doesn't contradict a workspace's own name.
 */
export function useShowsKiteDeskLogo(): boolean {
    const { name, branding } = usePage().props;

    return !branding?.logo && name === 'KiteDesk';
}
