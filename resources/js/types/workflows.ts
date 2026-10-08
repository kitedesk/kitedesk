import type {
    CategoryNode,
    CustomStatusOption,
    NamedRecord,
    Option,
    TicketField,
} from './support';

export type WorkflowTriggerEvent =
    | 'ticket_created'
    | 'ticket_updated'
    | 'customer_replied'
    | 'agent_replied'
    | 'note_added'
    | 'sla_breached'
    | 'ticket_idle'
    | 'manual';

export type WorkflowNodeType =
    | 'trigger'
    | 'if'
    | 'switch'
    | 'for_each'
    | 'filter'
    | 'set_variable'
    | 'wait'
    | 'wait_for_reply'
    | 'stop'
    | 'update_ticket'
    | 'add_tags'
    | 'remove_tags'
    | 'auto_assign'
    | 'add_note'
    | 'reply'
    | 'send_email'
    | 'notify'
    | 'add_cc'
    | 'http_request'
    | 'ai_classify'
    | 'ai_prompt'
    | 'ai_summary_note'
    | 'ai_draft_note';

/** A node's settings; each node type reads its own keys. */
export type WorkflowNodeData = Record<string, unknown>;

export type WorkflowGraphNode = {
    id: string;
    type: WorkflowNodeType;
    position: { x: number; y: number };
    data: WorkflowNodeData;
};

export type WorkflowGraphEdge = {
    id: string;
    source: string;
    sourceHandle: string;
    target: string;
};

export type WorkflowGraph = {
    nodes: WorkflowGraphNode[];
    edges: WorkflowGraphEdge[];
};

export type Condition = {
    field: string;
    operator: string;
    value: string;
};

export type ConditionGroup = {
    match: 'all' | 'any';
    conditions: (Condition | ConditionGroup)[];
};

export type WorkflowOptions = {
    triggers: Option<WorkflowTriggerEvent>[];
    operators: Option[];
    anchors: Option[];
    statuses: Option[];
    customStatuses: CustomStatusOption[];
    priorities: Option[];
    types: Option[];
    channels: Option[];
    categories: CategoryNode[];
    groups: NamedRecord[];
    organizations: NamedRecord[];
    agents: NamedRecord[];
    fields: TicketField[];
    /** Whether the AI assistant is set up, for the AI steps. */
    aiAvailable: boolean;
};

export type EditableWorkflow = {
    id: number | null;
    name: string;
    description: string | null;
    is_active: boolean;
    max_runs_per_ticket: number;
    apply_to_existing: boolean;
    disabled_reason: string | null;
    graph: WorkflowGraph;
};

export type WorkflowSummary = {
    id: number;
    name: string;
    description: string | null;
    is_active: boolean;
    trigger: WorkflowTriggerEvent;
    trigger_label: string;
    disabled_reason: string | null;
    steps: number;
    runs_last_week: number;
    failures_last_week: number;
    last_run_at: string | null;
};

export type WorkflowTemplate = {
    key: string;
    name: string;
    description: string;
};

export type RunStatus =
    | 'running'
    | 'waiting'
    | 'completed'
    | 'stopped'
    | 'failed'
    | 'cancelled';

export type WorkflowRunSummary = {
    id: number;
    status: RunStatus;
    status_label: string;
    trigger_event: string;
    ticket: { id: number; number: string; subject: string };
    error: string | null;
    resume_at: string | null;
    started_at: string | null;
    finished_at: string | null;
};

export type StepStatus = 'succeeded' | 'skipped' | 'waiting' | 'failed';

export type WorkflowStep = {
    node_id: string;
    node_type: WorkflowNodeType;
    status: StepStatus;
    handle: string | null;
    output: Record<string, unknown>;
    iteration: number | null;
    error: string | null;
    executed_at?: string;
};

export type SelectedRun = {
    id: number;
    graph: WorkflowGraph;
    vars: Record<string, unknown>;
    steps: WorkflowStep[];
};

export type SimulationResult = {
    status: RunStatus;
    error: string | null;
    steps: WorkflowStep[];
};
