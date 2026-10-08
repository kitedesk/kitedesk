import { t } from '@/lib/i18n';
import type { Option, WorkflowOptions } from '@/types';

export type WorkflowField = {
    value: string;
    /** English key, translated where rendered. */
    label: string;
    group: string;
    /** Choices for the value; free text when null. */
    choices: Option[] | null;
};

export const WATCHED_FIELDS: Option[] = [
    { value: 'subject', label: 'Subject' },
    { value: 'ticket_status_id', label: 'Status' },
    { value: 'status', label: 'Status category' },
    { value: 'priority', label: 'Priority' },
    { value: 'type', label: 'Type' },
    { value: 'assignee_id', label: 'Assignee' },
    { value: 'group_id', label: 'Group' },
    { value: 'category_id', label: 'Category' },
    { value: 'tags', label: 'Tags' },
    { value: 'collaborators', label: 'CC' },
    { value: 'custom_fields', label: 'Custom fields' },
];

/**
 * Custom statuses as choices, named with their category ("Pending › Waiting on vendor").
 */
export function customStatusChoices(options: WorkflowOptions): Option[] {
    return options.customStatuses
        .filter((status) => status.is_active)
        .map((status) => {
            const category = options.statuses.find(
                (option) => option.value === status.category,
            )?.label;

            return {
                value: String(status.id),
                label:
                    category && category !== status.name
                        ? `${category} › ${status.name}`
                        : status.name,
            };
        });
}

const yesNo = (): Option[] => [
    { value: '1', label: t('Yes') },
    { value: '0', label: t('No') },
];

/**
 * Everything a condition (If, Filter, Switch) can test.
 */
export function workflowFields(options: WorkflowOptions): WorkflowField[] {
    const records = (items: { id: number; name: string }[]) =>
        items.map((item) => ({ value: String(item.id), label: item.name }));
    const categories = options.categories.flatMap((category) => [
        { value: String(category.id), label: category.name },
        ...(category.children ?? []).map((child) => ({
            value: String(child.id),
            label: `${category.name} › ${child.name}`,
        })),
    ]);

    return [
        { value: 'subject', label: 'Subject', group: 'Ticket', choices: null },
        {
            value: 'custom_status',
            label: 'Status',
            group: 'Ticket',
            choices: customStatusChoices(options),
        },
        {
            value: 'status',
            label: 'Status category',
            group: 'Ticket',
            choices: options.statuses,
        },
        {
            value: 'priority',
            label: 'Priority',
            group: 'Ticket',
            choices: options.priorities,
        },
        {
            value: 'type',
            label: 'Type',
            group: 'Ticket',
            choices: options.types,
        },
        {
            value: 'channel',
            label: 'Channel',
            group: 'Ticket',
            choices: options.channels,
        },
        {
            value: 'group',
            label: 'Group',
            group: 'Ticket',
            choices: records(options.groups),
        },
        {
            value: 'assignee',
            label: 'Assignee',
            group: 'Ticket',
            choices: records(options.agents),
        },
        {
            value: 'category',
            label: 'Category',
            group: 'Ticket',
            choices: categories,
        },
        { value: 'tags', label: 'Tags', group: 'Ticket', choices: null },
        {
            value: 'organization',
            label: 'Organization',
            group: 'Ticket',
            choices: records(options.organizations),
        },
        {
            value: 'requester_email',
            label: 'Requester email',
            group: 'Ticket',
            choices: null,
        },
        {
            value: 'requester_name',
            label: 'Requester name',
            group: 'Ticket',
            choices: null,
        },
        { value: 'collaborators', label: 'CC', group: 'Ticket', choices: null },
        {
            value: 'sla_breached',
            label: 'SLA breached',
            group: 'Ticket',
            choices: yesNo(),
        },
        {
            value: 'message_body',
            label: 'Message text',
            group: 'Message',
            choices: null,
        },
        {
            value: 'message_author',
            label: 'Message author',
            group: 'Message',
            choices: [
                { value: 'customer', label: t('Customer') },
                { value: 'agent', label: t('Agent') },
                { value: 'system', label: t('System') },
            ],
        },
        {
            value: 'hours_since_created',
            label: 'Hours since created',
            group: 'Time',
            choices: null,
        },
        {
            value: 'hours_since_updated',
            label: 'Hours since last update',
            group: 'Time',
            choices: null,
        },
        {
            value: 'hours_since_customer_reply',
            label: 'Hours since customer reply',
            group: 'Time',
            choices: null,
        },
        {
            value: 'hours_since_agent_reply',
            label: 'Hours since agent reply',
            group: 'Time',
            choices: null,
        },
        {
            value: 'in_business_hours',
            label: 'Within business hours',
            group: 'Time',
            choices: yesNo(),
        },
        {
            value: 'changed',
            label: 'Changed fields',
            group: 'Trigger',
            choices: WATCHED_FIELDS.map((field) => ({
                value: field.value,
                label: t(field.label),
            })),
        },
        ...options.fields.map((field) => ({
            value: `custom_fields.${field.key}`,
            label: field.label,
            group: 'Custom fields',
            choices:
                field.type === 'select'
                    ? (field.options ?? []).map((option) => ({
                          value: option,
                          label: option,
                      }))
                    : field.type === 'checkbox'
                      ? yesNo()
                      : null,
        })),
    ];
}

/**
 * The definition for a field, also for loop items that are tickets (`item.status`).
 */
export function findWorkflowField(
    fields: WorkflowField[],
    field: string,
): WorkflowField | undefined {
    const plain = field.startsWith('item.') ? field.slice(5) : field;

    return fields.find((definition) => definition.value === plain);
}

export const PLACEHOLDERS: { group: string; keys: string[] }[] = [
    {
        group: 'Ticket',
        keys: [
            'ticket.number',
            'ticket.subject',
            'ticket.status',
            'ticket.priority',
            'ticket.url',
        ],
    },
    {
        group: 'People',
        keys: [
            'requester.name',
            'requester.first_name',
            'requester.email',
            'assignee.name',
            'group.name',
        ],
    },
    { group: 'Message', keys: ['message.body'] },
    {
        group: 'Workflow',
        keys: ['workflow.name', 'item', 'loop.index', 'loop.count'],
    },
];
