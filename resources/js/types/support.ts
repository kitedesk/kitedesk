export type Option<T extends string | number = string> = {
    value: T;
    label: string;
};

export type TicketStatus =
    | 'new'
    | 'open'
    | 'pending'
    | 'on_hold'
    | 'solved'
    | 'closed';

export type TicketPriority = 'low' | 'normal' | 'high' | 'urgent';

/**
 * Palette admins pick status colors from (see App\\Domain\\Tickets\\Models\\CustomStatus::COLORS).
 */
export type StatusColor =
    | 'slate'
    | 'zinc'
    | 'amber'
    | 'orange'
    | 'rose'
    | 'violet'
    | 'blue'
    | 'sky'
    | 'teal'
    | 'emerald';

/**
 * An admin-defined status; `category` is the fixed status that drives ticket behavior.
 */
export type CustomStatusSummary = {
    id: number;
    name: string;
    color: StatusColor;
    category: TicketStatus;
};

export type CustomStatusOption = CustomStatusSummary & {
    is_active: boolean;
    is_default: boolean;
};

export type UserType = 'staff' | 'customer';

export type UserSummary = {
    id: number;
    name: string;
    email?: string;
    type: UserType;
    avatar?: string | null;
};

export type NamedRecord = {
    id: number;
    name: string;
};

export type TicketSla = {
    policy: string | null;
    first_response_due_at: string | null;
    next_reply_due_at: string | null;
    resolution_due_at: string | null;
    resolution_paused: boolean;
    breached: boolean;
};

export type Ticket = {
    id: number;
    /** How people refer to the ticket, e.g. "#42" or "TKT-00042". */
    number: string;
    subject: string;
    status: TicketStatus;
    /** Staff only. */
    custom_status?: CustomStatusSummary | null;
    priority: TicketPriority;
    type: string | null;
    channel: string;
    requester?: UserSummary;
    assignee?: UserSummary | null;
    group?: NamedRecord | null;
    collaborators?: UserSummary[];
    category?: TicketCategoryRef | null;
    form_id: number | null;
    organization?: NamedRecord | null;
    tags?: string[];
    custom_fields: Record<string, unknown>;
    sla?: TicketSla;
    latest_message?: { excerpt: string; is_internal: boolean } | null;
    messages_count?: number;
    first_responded_at: string | null;
    solved_at: string | null;
    created_at: string;
    updated_at: string;
};

export type Attachment = {
    id: number;
    name: string;
    size: number;
    mime_type: string | null;
    url: string;
};

export type TicketMessage = {
    id: number;
    body: string;
    is_internal: boolean;
    channel: string;
    author: UserSummary | null;
    /** Name of the workflow that wrote the message (staff only). */
    workflow?: string | null;
    /** Agent or group a handover note forwarded the ticket to (staff only). */
    forwarded_to?: string | null;
    /** Drafted with the AI assistant (staff only). */
    ai_assisted?: boolean;
    /** Sent by an AI client over MCP, on the author's behalf (staff only). */
    via_mcp?: boolean;
    attachments?: Attachment[];
    /** Secret requests and shared secrets sent with the reply. */
    secrets?: TicketSecret[];
    created_at: string;
};

export type SecretStatus =
    | 'pending'
    | 'available'
    | 'used_up'
    | 'expired'
    | 'revoked';

/**
 * A secret's card on a ticket; never carries the encrypted content.
 */
export type TicketSecret = {
    token: string;
    kind: 'request' | 'share';
    label: string;
    status: SecretStatus;
    status_label: string;
    views: number;
    max_views: number;
    expires_at: string;
    /** The page the customer opens after signing in. */
    url: string;
    /** Staff only. */
    creator?: { id: number; name: string } | null;
    can_reveal?: boolean;
    can_revoke?: boolean;
};

export type TicketFieldType =
    | 'text'
    | 'textarea'
    | 'number'
    | 'date'
    | 'checkbox'
    | 'select';

/**
 * A custom field in the current locale, as shown on a form.
 */
export type TicketField = {
    id: number;
    key: string;
    label: string;
    type: TicketFieldType;
    options: string[] | null;
    is_required: boolean;
    is_visible_to_customers: boolean;
};

export type TicketCategoryRef = {
    id: number;
    parent_id: number | null;
    name: string;
    path: string;
};

export type CategoryNode = {
    id: number;
    name: string;
    description: string;
    form_id: number | null;
    children?: CategoryNode[];
};

/**
 * Fields of each active form, keyed by form id.
 */
export type FormFields = Record<number, TicketField[]>;

/**
 * The customer's answer to the satisfaction survey; score is null while unanswered.
 */
export type TicketSatisfaction = {
    score: number | null;
    comment: string | null;
    sent_at: string | null;
    rated_at: string | null;
};

export type TicketOptions = {
    statuses: Option<TicketStatus>[];
    /** Every custom status, inactive ones too (for labels). */
    customStatuses: CustomStatusOption[];
    priorities: Option<TicketPriority>[];
    types: Option[];
    agents: NamedRecord[];
    groups: NamedRecord[];
    categories: CategoryNode[];
    tags: string[];
};

export type Paginated<T> = {
    data: T[];
    links: {
        first: string | null;
        last: string | null;
        prev: string | null;
        next: string | null;
    };
    meta: {
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        from: number | null;
        to: number | null;
        path: string;
    };
};

export type AgentNavView = {
    key: string;
    label: string;
    count: number;
    /** Present for views agents saved from the queue filters. */
    saved?: {
        id: number;
        shared: boolean;
        manageable: boolean;
        /** Null follows the agent's preference. */
        layout: TicketsLayout | null;
    };
    /** Present for the queue of every ticket in one custom status. */
    status?: { id: number; color: StatusColor; category: TicketStatus };
};

export type AgentNav = {
    views: AgentNavView[];
    unreadNotifications: number;
} | null;

export type TicketsLayout = 'list' | 'board';

export type BoardGroupBy = 'status' | 'priority' | 'assignee' | 'group';

export type BoardCardField =
    | 'requester'
    | 'assignee'
    | 'priority'
    | 'status'
    | 'group'
    | 'sla'
    | 'tags'
    | 'latest_message'
    | 'updated';

export type BoardLanePreferences = { order: string[]; hidden: string[] };

export type BoardPreferences = {
    layout: TicketsLayout;
    group_by: BoardGroupBy;
    columns: Partial<Record<BoardGroupBy, BoardLanePreferences>>;
    card_fields: BoardCardField[];
};

export type BoardLaneSummary = {
    /** The value a dropped ticket gets: a status id, a priority, a user or group id, or `none`. */
    key: string;
    label: string;
    color: StatusColor | null;
};

export type BoardLane = BoardLaneSummary & {
    count: number;
    has_more: boolean;
    tickets: Ticket[];
};

export type TicketBoardData = {
    group_by: BoardGroupBy;
    lanes: BoardLane[];
    /** Every lane, hidden ones too, in the agent's order. */
    available: (BoardLaneSummary & { hidden: boolean })[];
};
