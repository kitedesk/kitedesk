import {
    AtSign,
    Bell,
    Clock,
    Filter,
    GitBranch,
    Globe,
    Hourglass,
    Mail,
    MessageCircleQuestion,
    MessageSquareReply,
    NotebookPen,
    OctagonX,
    PenLine,
    Pencil,
    Repeat,
    ScrollText,
    Shuffle,
    Sparkles,
    Tag,
    Tags,
    UserCheck,
    Variable,
    Zap,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { t } from '@/lib/i18n';
import type {
    Condition,
    ConditionGroup,
    WorkflowNodeData,
    WorkflowNodeType,
    WorkflowOptions,
} from '@/types';

export type NodeCategory = 'trigger' | 'logic' | 'action' | 'ai';

export type NodeOutput = { id: string; label: string };

export type NodeDefinition = {
    type: WorkflowNodeType;
    category: NodeCategory;
    icon: LucideIcon;
    /** English key, translated where rendered. */
    label: string;
    description: string;
    outputs: (data: WorkflowNodeData) => NodeOutput[];
    defaults: () => WorkflowNodeData;
    summary: (data: WorkflowNodeData, options: WorkflowOptions) => string;
};

const single = (): NodeOutput[] => [{ id: 'out', label: '' }];

const str = (value: unknown): string =>
    typeof value === 'string' || typeof value === 'number' ? String(value) : '';

const list = (value: unknown): string[] =>
    Array.isArray(value) ? value.map(String) : [];

const labelOf = (
    options: { value: string | number; label: string }[],
    value: unknown,
): string =>
    options.find((option) => String(option.value) === str(value))?.label ??
    str(value);

const nameOf = (records: { id: number; name: string }[], value: unknown) =>
    records.find((record) => String(record.id) === str(value))?.name ??
    `#${str(value)}`;

const excerpt = (html: unknown, length = 60): string => {
    const text = str(html)
        .replace(/<[^>]+>/g, ' ')
        .replace(/\s+/g, ' ')
        .trim();

    return text.length > length ? `${text.slice(0, length)}…` : text;
};

const duration = (data: WorkflowNodeData): string => {
    const amount = Number(data.amount ?? 0);
    const unit = str(data.unit);

    return unit === 'days'
        ? t(':count day|:count days', { count: amount })
        : unit === 'hours'
          ? t(':count hour|:count hours', { count: amount })
          : t(':count minute|:count minutes', { count: amount });
};

export const isGroup = (
    item: Condition | ConditionGroup,
): item is ConditionGroup => 'conditions' in item;

export function countConditions(group: unknown): number {
    if (!group || typeof group !== 'object' || !('conditions' in group)) {
        return 0;
    }

    return (group as ConditionGroup).conditions.reduce(
        (total, item) => total + (isGroup(item) ? countConditions(item) : 1),
        0,
    );
}

const conditionsSummary = (data: WorkflowNodeData): string => {
    const count = countConditions(data.conditions);

    return count === 0
        ? t('No conditions yet')
        : t(':count condition|:count conditions', { count });
};

export const COLLECTION_LABELS: Record<string, string> = {
    tags: 'Tags on the ticket',
    collaborators: 'People in CC',
    messages: 'Messages',
    customer_messages: 'Customer messages',
    attachments: 'Attachments',
    linked_tickets: 'Related tickets',
    requester_tickets: "Requester's other tickets",
    organization_tickets: "Organization's other tickets",
    find_tickets: 'Find tickets',
    variable: 'A list variable',
};

export const NODE_DEFINITIONS: Record<WorkflowNodeType, NodeDefinition> = {
    trigger: {
        type: 'trigger',
        category: 'trigger',
        icon: Zap,
        label: 'Trigger',
        description: 'What starts the workflow',
        outputs: single,
        defaults: () => ({ event: 'ticket_created' }),
        summary: (data, options) => {
            const event = labelOf(options.triggers, data.event);

            if (data.event === 'ticket_idle') {
                return `${event} · ${labelOf(options.anchors, data.anchor)} + ${duration(data)}`;
            }

            if (data.event === 'ticket_updated' && list(data.fields).length) {
                return `${event} · ${list(data.fields).join(', ')}`;
            }

            return event;
        },
    },
    if: {
        type: 'if',
        category: 'logic',
        icon: GitBranch,
        label: 'If',
        description: 'Continue on “true” or “false”',
        outputs: () => [
            { id: 'true', label: 'true' },
            { id: 'false', label: 'false' },
        ],
        defaults: () => ({ conditions: { match: 'all', conditions: [] } }),
        summary: conditionsSummary,
    },
    switch: {
        type: 'switch',
        category: 'logic',
        icon: Shuffle,
        label: 'Switch',
        description: 'Branch on the value of a field',
        outputs: (data) => [
            ...(Array.isArray(data.cases) ? data.cases : []).map(
                (item: { id: string; value: string }) => ({
                    id: item.id,
                    label: item.value || '…',
                }),
            ),
            { id: 'default', label: 'default' },
        ],
        defaults: () => ({
            field: 'priority',
            cases: [{ id: 'c1', value: 'urgent' }],
        }),
        summary: (data) =>
            t('Switch on :field', { field: str(data.field) || '…' }),
    },
    for_each: {
        type: 'for_each',
        category: 'logic',
        icon: Repeat,
        label: 'For each',
        description: 'Repeat steps for every item of a list',
        outputs: () => [
            { id: 'each', label: 'each' },
            { id: 'done', label: 'done' },
        ],
        defaults: () => ({ collection: 'linked_tickets' }),
        summary: (data) => t(COLLECTION_LABELS[str(data.collection)] ?? ''),
    },
    filter: {
        type: 'filter',
        category: 'logic',
        icon: Filter,
        label: 'Filter',
        description: 'Continue only when conditions match',
        outputs: single,
        defaults: () => ({ conditions: { match: 'all', conditions: [] } }),
        summary: conditionsSummary,
    },
    set_variable: {
        type: 'set_variable',
        category: 'logic',
        icon: Variable,
        label: 'Set variable',
        description: 'Remember a value for later steps',
        outputs: single,
        defaults: () => ({ name: 'value', value: '', kind: 'text' }),
        summary: (data) => `vars.${str(data.name)} = ${str(data.value)}`,
    },
    wait: {
        type: 'wait',
        category: 'logic',
        icon: Clock,
        label: 'Wait',
        description: 'Pause before the next step',
        outputs: single,
        defaults: () => ({ amount: 1, unit: 'hours', business_hours: false }),
        summary: (data) =>
            data.business_hours
                ? t(':duration (business hours)', { duration: duration(data) })
                : duration(data),
    },
    wait_for_reply: {
        type: 'wait_for_reply',
        category: 'logic',
        icon: Hourglass,
        label: 'Wait for reply',
        description: 'Wait for the customer, or time out',
        outputs: () => [
            { id: 'replied', label: 'replied' },
            { id: 'timeout', label: 'timeout' },
        ],
        defaults: () => ({ amount: 2, unit: 'days' }),
        summary: (data) => t('Up to :duration', { duration: duration(data) }),
    },
    stop: {
        type: 'stop',
        category: 'logic',
        icon: OctagonX,
        label: 'Stop',
        description: 'End the run or leave the loop',
        outputs: () => [],
        defaults: () => ({ scope: 'run' }),
        summary: (data) =>
            data.scope === 'loop' ? t('Leave the loop') : t('End the run'),
    },
    update_ticket: {
        type: 'update_ticket',
        category: 'action',
        icon: Pencil,
        label: 'Update ticket',
        description: 'Status, priority, group, assignee…',
        outputs: single,
        defaults: () => ({}),
        summary: (data, options) => {
            const parts: string[] = [];

            const statusId = str(data.ticket_status_id);

            if (statusId) {
                parts.push(
                    options.customStatuses.find(
                        (status) => String(status.id) === statusId,
                    )?.name ?? statusId,
                );
            } else if (data.status) {
                parts.push(labelOf(options.statuses, data.status));
            }

            if (data.priority) {
                parts.push(labelOf(options.priorities, data.priority));
            }

            if (data.group_id) {
                parts.push(nameOf(options.groups, data.group_id));
            }

            if (data.assignee_id) {
                parts.push(
                    data.assignee_id === 'none'
                        ? t('Unassigned')
                        : nameOf(options.agents, data.assignee_id),
                );
            }

            return parts.join(' · ') || t('Nothing to change yet');
        },
    },
    add_tags: {
        type: 'add_tags',
        category: 'action',
        icon: Tag,
        label: 'Add tags',
        description: 'Tag the ticket',
        outputs: single,
        defaults: () => ({ tags: [] }),
        summary: (data) => list(data.tags).join(', ') || '…',
    },
    remove_tags: {
        type: 'remove_tags',
        category: 'action',
        icon: Tags,
        label: 'Remove tags',
        description: 'Remove tags from the ticket',
        outputs: single,
        defaults: () => ({ tags: [] }),
        summary: (data) => list(data.tags).join(', ') || '…',
    },
    auto_assign: {
        type: 'auto_assign',
        category: 'action',
        icon: UserCheck,
        label: 'Assign automatically',
        description: "Use the group's assignment mode",
        outputs: single,
        defaults: () => ({}),
        summary: () => t('Round robin or least busy, per group'),
    },
    add_note: {
        type: 'add_note',
        category: 'action',
        icon: NotebookPen,
        label: 'Add internal note',
        description: 'Only agents see it',
        outputs: single,
        defaults: () => ({ body: '' }),
        summary: (data) => excerpt(data.body) || '…',
    },
    reply: {
        type: 'reply',
        category: 'action',
        icon: MessageSquareReply,
        label: 'Reply to requester',
        description: 'A public reply, emailed to the customer',
        outputs: single,
        defaults: () => ({ body: '', status_after: '' }),
        summary: (data) => excerpt(data.body) || '…',
    },
    send_email: {
        type: 'send_email',
        category: 'action',
        icon: Mail,
        label: 'Send email',
        description: 'A standalone email about the ticket',
        outputs: single,
        defaults: () => ({ to: 'requester', subject: '', body: '' }),
        summary: (data) => str(data.subject) || '…',
    },
    notify: {
        type: 'notify',
        category: 'action',
        icon: Bell,
        label: 'Notify agents',
        description: 'In the app and by email',
        outputs: single,
        defaults: () => ({ to: 'assignee', message: '' }),
        summary: (data) => str(data.message) || '…',
    },
    add_cc: {
        type: 'add_cc',
        category: 'action',
        icon: AtSign,
        label: 'Add CC',
        description: 'Copy people on the ticket',
        outputs: single,
        defaults: () => ({ emails: [] }),
        summary: (data) => list(data.emails).join(', ') || '…',
    },
    ai_classify: {
        type: 'ai_classify',
        category: 'ai',
        icon: Sparkles,
        label: 'Classify with AI',
        description: 'Infer category, priority, intent and more',
        outputs: () => [
            { id: 'confident', label: 'confident' },
            { id: 'unsure', label: 'unsure' },
            { id: 'failed', label: 'failed' },
        ],
        defaults: () => ({
            fields: {
                category: { enabled: true, apply: true },
                priority: { enabled: true, apply: true },
                type: { enabled: false, apply: false },
                tags: { enabled: false, apply: false, options: [] },
                sentiment: { enabled: true },
                language: { enabled: false },
                intent: { enabled: false, options: [] },
            },
            keep_existing: true,
            min_confidence: 70,
            save_as: 'ai',
        }),
        summary: (data) => {
            const fields = (data.fields ?? {}) as Record<
                string,
                { enabled?: boolean }
            >;

            return (
                Object.entries(fields)
                    .filter(([, field]) => field?.enabled)
                    .map(([key]) => t(AI_FIELD_LABELS[key] ?? key))
                    .join(', ') || '…'
            );
        },
    },
    ai_prompt: {
        type: 'ai_prompt',
        category: 'ai',
        icon: MessageCircleQuestion,
        label: 'Ask AI',
        description: 'Save the answer to a prompt as a variable',
        outputs: () => [
            { id: 'out', label: '' },
            { id: 'failed', label: 'failed' },
        ],
        defaults: () => ({
            prompt: '',
            include_conversation: true,
            save_as: 'answer',
        }),
        summary: (data) =>
            `vars.${str(data.save_as)} = ${excerpt(data.prompt, 40) || '…'}`,
    },
    ai_summary_note: {
        type: 'ai_summary_note',
        category: 'ai',
        icon: ScrollText,
        label: 'Add an AI summary note',
        description: 'An internal note summarizing the ticket',
        outputs: () => [
            { id: 'out', label: '' },
            { id: 'failed', label: 'failed' },
        ],
        defaults: () => ({}),
        summary: () => t('Internal note'),
    },
    ai_draft_note: {
        type: 'ai_draft_note',
        category: 'ai',
        icon: PenLine,
        label: 'Add an AI draft reply note',
        description: 'A suggested reply as an internal note, never sent',
        outputs: () => [
            { id: 'out', label: '' },
            { id: 'failed', label: 'failed' },
        ],
        defaults: () => ({ instruction: '', use_articles: true }),
        summary: (data) => excerpt(data.instruction) || t('Internal note'),
    },
    http_request: {
        type: 'http_request',
        category: 'action',
        icon: Globe,
        label: 'HTTP request',
        description: 'Call another system',
        outputs: single,
        defaults: () => ({
            method: 'POST',
            url: 'https://',
            headers: [],
            body: '',
        }),
        summary: (data) => `${str(data.method)} ${str(data.url)}`,
    },
};

export const CATEGORY_LABELS: Record<NodeCategory, string> = {
    trigger: 'Trigger',
    logic: 'Logic',
    action: 'Actions',
    ai: 'AI',
};

export const CATEGORY_STYLES: Record<NodeCategory, string> = {
    trigger: 'bg-amber-500/15 text-amber-600 dark:text-amber-400',
    logic: 'bg-violet-500/15 text-violet-600 dark:text-violet-400',
    action: 'bg-sky-500/15 text-sky-600 dark:text-sky-400',
    ai: 'bg-fuchsia-500/15 text-fuchsia-600 dark:text-fuchsia-400',
};

/**
 * What "Classify with AI" can infer (English keys, translated where rendered).
 */
export const AI_FIELD_LABELS: Record<string, string> = {
    category: 'Category',
    priority: 'Priority',
    type: 'Type',
    tags: 'Tags',
    sentiment: 'Sentiment',
    language: 'Language',
    intent: 'Intent',
};

export const STATUS_STYLES: Record<string, string> = {
    succeeded: 'ring-2 ring-emerald-500',
    skipped: 'ring-2 ring-zinc-400',
    waiting: 'ring-2 ring-amber-500',
    failed: 'ring-2 ring-destructive',
};
