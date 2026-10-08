import type { HTMLAttributes } from 'react';
import { cn } from '@/lib/utils';

/**
 * Marks the field next to it as invalid (red label and border, see app.css). The message
 * is shown in a toast by useFlashToast and kept here for screen readers.
 */
export default function InputError({
    message,
    className = '',
    ...props
}: HTMLAttributes<HTMLParagraphElement> & { message?: string }) {
    return message ? (
        <p
            {...props}
            data-slot="field-error"
            className={cn('sr-only', className)}
        >
            {message}
        </p>
    ) : null;
}
