import { usePage } from '@inertiajs/react';
import { useEffect, useRef } from 'react';
import InputError from '@/components/input-error';
import { getLocale } from '@/lib/i18n';

type TurnstileApi = {
    render: (
        container: HTMLElement,
        options: Record<string, unknown>,
    ) => string;
    reset: (widgetId: string) => void;
    remove: (widgetId: string) => void;
};

declare global {
    interface Window {
        turnstile?: TurnstileApi;
    }
}

const SCRIPT_URL =
    'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit';

let scriptLoading: Promise<void> | null = null;

function loadTurnstile(): Promise<void> {
    if (window.turnstile) {
        return Promise.resolve();
    }

    scriptLoading ??= new Promise((resolve, reject) => {
        const script = document.createElement('script');
        script.src = SCRIPT_URL;
        script.async = true;
        script.onload = () => resolve();
        script.onerror = () => reject(new Error('Turnstile failed to load'));
        document.head.appendChild(script);
    });

    return scriptLoading;
}

/**
 * Cloudflare Turnstile check for public forms. Renders nothing unless a site key is configured.
 * The widget adds a `cf-turnstile-response` field to the surrounding form; `onToken` also
 * reports it for forms built with `useForm`. Change `resetKey` to ask for a fresh token
 * (tokens can only be used once).
 */
export function Captcha({
    onToken,
    resetKey,
    error,
}: {
    onToken?: (token: string) => void;
    resetKey?: unknown;
    error?: string;
}) {
    const { captchaSiteKey } = usePage().props;
    const container = useRef<HTMLDivElement>(null);
    const widget = useRef<string | null>(null);
    const tokenCallback = useRef(onToken);
    tokenCallback.current = onToken;

    useEffect(() => {
        if (!captchaSiteKey) {
            return;
        }

        let cancelled = false;

        void loadTurnstile().then(() => {
            if (cancelled || !container.current || !window.turnstile) {
                return;
            }

            widget.current = window.turnstile.render(container.current, {
                sitekey: captchaSiteKey,
                language: getLocale().replace('_', '-').toLowerCase(),
                callback: (token: string) => tokenCallback.current?.(token),
                'expired-callback': () => tokenCallback.current?.(''),
            });
        });

        return () => {
            cancelled = true;

            if (widget.current && window.turnstile) {
                window.turnstile.remove(widget.current);
                widget.current = null;
            }
        };
    }, [captchaSiteKey]);

    useEffect(() => {
        if (resetKey !== undefined && widget.current && window.turnstile) {
            window.turnstile.reset(widget.current);
            tokenCallback.current?.('');
        }
    }, [resetKey]);

    if (!captchaSiteKey) {
        return null;
    }

    return (
        <div className="grid gap-2">
            <div ref={container} />
            <InputError message={error} />
        </div>
    );
}
