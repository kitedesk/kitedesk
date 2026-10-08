import { usePage } from '@inertiajs/react';

/**
 * Features a plan can leave out (see App\Domain\Entitlements\Enums\Feature). The
 * open-source edition includes all of them.
 */
export type Feature =
    | 'ai'
    | 'mcp'
    | 'api'
    | 'workflows'
    | 'custom_branding'
    | 'sla'
    | 'custom_roles'
    | 'satisfaction'
    | 'webhooks'
    | 'reports'
    | 'custom_email_templates'
    | 'widget';

export type Entitlements = {
    features: Record<Feature, boolean>;
    /** The most the plan allows of each; null for no limit. */
    limits: Record<
        | 'agent_seats'
        | 'mailboxes'
        | 'active_workflows'
        | 'custom_fields'
        | 'ticket_forms',
        number | null
    >;
};

/**
 * What the installation's plan includes, for hiding what it doesn't. The server still
 * checks every action.
 */
export function useEntitlements() {
    const { entitlements } = usePage().props;
    const includes = (feature: Feature): boolean =>
        entitlements?.features?.[feature] !== false;

    return { includes, limits: entitlements?.limits };
}
