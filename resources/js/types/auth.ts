export type User = {
    id: number;
    name: string;
    email: string;
    avatar?: string | null;
    timezone?: string | null;
    locale?: string | null;
    job_title?: string | null;
    phone?: string | null;
    signature?: string | null;
    email_verified_at: string | null;
    two_factor_enabled?: boolean;
    type: import('./support').UserType;
    /** Agents who are away don't get tickets from auto-assignment. */
    is_available?: boolean;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
};

/**
 * Permission names granted by staff roles (see App\\Domain\\Accounts\\Enums\\Permission).
 */
export type CorePermission =
    | 'tickets.create'
    | 'tickets.reply'
    | 'tickets.update'
    | 'tickets.merge'
    | 'tickets.forward'
    | 'tickets.delete'
    | 'tickets.run_workflows'
    | 'tickets.receive_assignments'
    | 'tickets.secrets'
    | 'tickets.use_ai'
    | 'views.share'
    | 'canned_responses.share'
    | 'reports.view'
    | 'reports.export'
    | 'admin.branding'
    | 'admin.team'
    | 'admin.ticket_setup'
    | 'admin.sla'
    | 'admin.email'
    | 'admin.automation'
    | 'admin.help_center'
    | 'admin.integrations';

/**
 * Core permissions plus those registered by KiteDesk packages (`KiteDesk::permissions()`).
 */
export type Permission = CorePermission | (string & {});

export type TicketAccess = 'all' | 'groups' | 'assigned';

export type Auth = {
    user: User;
    isStaff: boolean;
    /** Name of the staff member's role; null for customers. */
    role: string | null;
    ticketAccess: TicketAccess | null;
    /** What the signed-in user's role allows; all false for customers and guests. */
    can: Partial<Record<Permission, boolean>>;
};

export type Passkey = {
    id: number;
    name: string;
    authenticator: string | null;
    created_at_diff: string;
    last_used_at_diff: string | null;
};

export type TwoFactorSetupData = {
    svg: string;
    url: string;
};

export type TwoFactorSecretKey = {
    secretKey: string;
};
