import type { CSSProperties, HTMLAttributes } from 'react';
import { cn } from '@/lib/utils';

const mask: CSSProperties = {
    maskImage: 'url(/images/brand/icon.png)',
    WebkitMaskImage: 'url(/images/brand/icon.png)',
    maskSize: 'contain',
    WebkitMaskSize: 'contain',
    maskRepeat: 'no-repeat',
    WebkitMaskRepeat: 'no-repeat',
    maskPosition: 'center',
    WebkitMaskPosition: 'center',
};

/**
 * The KiteDesk kite, drawn in the current text color so it follows the theme.
 */
export default function AppLogoIcon({
    className,
    style,
    ...props
}: HTMLAttributes<HTMLSpanElement>) {
    return (
        <span
            aria-hidden
            {...props}
            style={{ ...mask, ...style }}
            className={cn('inline-block shrink-0 bg-current', className)}
        />
    );
}
