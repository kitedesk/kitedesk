import { router } from '@inertiajs/react';
import { useEffect } from 'react';
import { toast } from 'sonner';
import { t } from '@/lib/i18n';
import type { FlashToast } from '@/types/ui';

/**
 * Validation messages from a failed visit, without duplicates. Named error bags nest them
 * one level deeper.
 */
function errorMessages(errors: Record<string, unknown>): string[] {
    const messages = Object.values(errors).flatMap((value) =>
        typeof value === 'string'
            ? [value]
            : value && typeof value === 'object'
              ? errorMessages(value as Record<string, unknown>)
              : [],
    );

    return [...new Set(messages)];
}

/**
 * Shows flashed toasts, and a toast for every request that fails validation (the fields
 * themselves are only marked red, see InputError).
 */
export function useFlashToast(): void {
    useEffect(() => {
        const stopFlash = router.on('flash', (event) => {
            const flash = (event as CustomEvent).detail?.flash;
            const data = flash?.toast as FlashToast | undefined;

            if (!data) {
                return;
            }

            toast[data.type](data.message);
        });

        const stopErrors = router.on('error', (event) => {
            const messages = errorMessages(event.detail.errors);

            if (messages.length === 0) {
                return;
            }

            if (messages.length === 1) {
                toast.error(messages[0]);

                return;
            }

            toast.error(t('Please fix the following:'), {
                description: messages
                    .map((message) => `• ${message}`)
                    .join('\n'),
                descriptionClassName: 'whitespace-pre-line',
            });
        });

        return () => {
            stopFlash();
            stopErrors();
        };
    }, []);
}

/**
 * Toast an error raised in the browser (not by a server response), e.g. a cancelled passkey prompt.
 */
export function useErrorToast(error: string | null | undefined): void {
    useEffect(() => {
        if (error) {
            toast.error(error);
        }
    }, [error]);
}
