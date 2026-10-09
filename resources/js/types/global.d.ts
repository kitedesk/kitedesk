import type { Auth } from '@/types/auth';
import type { Branding } from '@/types/branding';
import type { AgentNav } from '@/types/support';

declare module 'react' {
    interface InputHTMLAttributes<T> {
        passwordrules?: string;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            /** The brand name admins chose (APP_NAME by default). */
            name: string;
            branding: Branding;
            auth: Auth;
            sidebarOpen: boolean;
            agentNav: AgentNav;
            /** The language of this request, e.g. "en" or "pt_BR". */
            locale: string;
            /** JSON translation strings for that language (English keys). */
            translations: Record<string, string>;
            /** Whether people without an account may submit requests. */
            guestTickets: boolean;
            /** Cloudflare Turnstile site key, when CAPTCHA is configured. */
            captchaSiteKey: string | null;
            /** What the plan includes; everything in the open-source edition. */
            entitlements: import('@/hooks/use-entitlements').Entitlements;
            /** Prefix of every broadcast channel name. */
            broadcastScope: string;
            [key: string]: unknown;
        };
        flashDataType: {
            toast?: import('@/types/ui').FlashToast;
            /** One-time secrets revealed right after creation/rotation. */
            webhookSecret?: string;
            newToken?: string;
            [key: string]: unknown;
        };
    }
}
