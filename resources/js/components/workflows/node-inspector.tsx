import { AlertCircle, Plus, Trash2, X } from 'lucide-react';
import { useRef } from 'react';
import type { ReactNode } from 'react';
import { MultiSelectChips } from '@/components/admin/multi-select-chips';
import { RichTextEditor } from '@/components/tickets/rich-text-editor';
import type { RichTextEditorHandle } from '@/components/tickets/rich-text-editor';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    ConditionBuilder,
    FieldSelect,
} from '@/components/workflows/condition-builder';
import {
    Field,
    ListInput,
    OptionSelect,
    PlaceholderMenu,
    TemplateInput,
    TemplateTextarea,
} from '@/components/workflows/inspector-fields';
import {
    AI_FIELD_LABELS,
    CATEGORY_STYLES,
    COLLECTION_LABELS,
    NODE_DEFINITIONS,
} from '@/components/workflows/node-catalog';
import {
    customStatusChoices,
    WATCHED_FIELDS,
    workflowFields,
} from '@/components/workflows/workflow-fields';
import { useTranslation } from '@/hooks/use-translation';
import { cn } from '@/lib/utils';
import type {
    ConditionGroup,
    Option,
    WorkflowNodeData,
    WorkflowNodeType,
    WorkflowOptions,
    WorkflowStep,
} from '@/types';

type FormProps = {
    data: WorkflowNodeData;
    set: (patch: WorkflowNodeData) => void;
    options: WorkflowOptions;
    variables: string[];
};

const str = (value: unknown): string =>
    typeof value === 'string' || typeof value === 'number' ? String(value) : '';

const strings = (value: unknown): string[] =>
    Array.isArray(value) ? value.map(String) : [];

const TICKET_COLLECTIONS = [
    'linked_tickets',
    'requester_tickets',
    'organization_tickets',
    'find_tickets',
];

const TICKET_ACTIONS: WorkflowNodeType[] = [
    'update_ticket',
    'add_tags',
    'remove_tags',
    'auto_assign',
    'add_note',
    'reply',
    'send_email',
    'notify',
    'add_cc',
    'ai_classify',
    'ai_prompt',
    'ai_summary_note',
    'ai_draft_note',
];

function useUnits(withMinutes: boolean): Option[] {
    const { t } = useTranslation();

    return [
        ...(withMinutes ? [{ value: 'minutes', label: t('Minutes') }] : []),
        { value: 'hours', label: t('Hours') },
        { value: 'days', label: t('Days') },
    ];
}

function Duration({
    data,
    set,
    withMinutes = true,
}: FormProps & { withMinutes?: boolean }) {
    const { t } = useTranslation();
    const units = useUnits(withMinutes);

    return (
        <div className="grid grid-cols-2 gap-2">
            <Field label={t('Amount')}>
                <Input
                    type="number"
                    min={1}
                    max={1000}
                    value={str(data.amount)}
                    onChange={(event) =>
                        set({ amount: Number(event.target.value) })
                    }
                />
            </Field>
            <Field label={t('Unit')}>
                <OptionSelect
                    label={t('Unit')}
                    value={str(data.unit)}
                    options={units}
                    onChange={(unit) => set({ unit })}
                />
            </Field>
        </div>
    );
}

function RichBody({
    label,
    data,
    set,
    variables,
}: FormProps & { label: string }) {
    const editor = useRef<RichTextEditorHandle>(null);

    return (
        <div className="grid gap-1.5">
            <div className="flex items-center justify-between gap-2">
                <Label className="text-xs">{label}</Label>
                <PlaceholderMenu
                    variables={variables}
                    onInsert={(token) => editor.current?.insertContent(token)}
                />
            </div>
            <div className="rounded-md border bg-background">
                <RichTextEditor
                    ref={editor}
                    value={str(data.body)}
                    onChange={(body) => set({ body })}
                    minHeight="7rem"
                />
            </div>
        </div>
    );
}

function TriggerForm({ data, set, options }: FormProps) {
    const { t } = useTranslation();
    const event = str(data.event);

    return (
        <>
            <Field label={t('When')}>
                <OptionSelect
                    label={t('When')}
                    value={event}
                    options={options.triggers}
                    onChange={(next) => set({ event: next })}
                />
            </Field>
            {event === 'ticket_updated' && (
                <Field
                    label={t('Only when these change')}
                    hint={t('Leave empty for any change.')}
                >
                    <MultiSelectChips
                        options={WATCHED_FIELDS.map((field) => ({
                            value: field.value,
                            label: t(field.label),
                        }))}
                        value={strings(data.fields)}
                        onChange={(fields) => set({ fields })}
                    />
                </Field>
            )}
            {[
                'ticket_created',
                'customer_replied',
                'agent_replied',
                'note_added',
            ].includes(event) && (
                <Field
                    label={t('Channels')}
                    hint={t('Leave empty for every channel.')}
                >
                    <MultiSelectChips
                        options={options.channels.map((channel) => ({
                            value: String(channel.value),
                            label: channel.label,
                        }))}
                        value={strings(data.channels)}
                        onChange={(channels) => set({ channels })}
                    />
                </Field>
            )}
            {event === 'ticket_idle' && (
                <>
                    <Field label={t('Counting from')}>
                        <OptionSelect
                            label={t('Counting from')}
                            value={str(data.anchor)}
                            options={options.anchors}
                            onChange={(anchor) => set({ anchor })}
                        />
                    </Field>
                    <Duration
                        data={data}
                        set={set}
                        options={options}
                        variables={[]}
                        withMinutes={false}
                    />
                    <Field
                        label={t('Ticket statuses')}
                        hint={t(
                            'Leave empty for all unresolved tickets. Checked every 5 minutes.',
                        )}
                    >
                        <MultiSelectChips
                            options={options.statuses.map((status) => ({
                                value: String(status.value),
                                label: status.label,
                            }))}
                            value={strings(data.statuses)}
                            onChange={(statuses) => set({ statuses })}
                        />
                    </Field>
                </>
            )}
            {event === 'manual' && (
                <Field
                    label={t('Who can run it')}
                    hint={t('From the ticket page, under “Run workflow”.')}
                >
                    <OptionSelect
                        label={t('Who can run it')}
                        value={str(data.run_by) || 'agents'}
                        options={[
                            {
                                value: 'agents',
                                label: t('Agents and administrators'),
                            },
                            {
                                value: 'admins',
                                label: t('Administrators only'),
                            },
                        ]}
                        onChange={(run_by) => set({ run_by })}
                    />
                </Field>
            )}
        </>
    );
}

function ConditionsForm({ data, set, options }: FormProps) {
    const { t } = useTranslation();

    return (
        <ConditionBuilder
            value={
                (data.conditions as ConditionGroup | undefined) ?? {
                    match: 'all',
                    conditions: [],
                }
            }
            onChange={(conditions) => set({ conditions })}
            fields={workflowFields(options)}
            operators={options.operators.map((operator) => ({
                ...operator,
                label: t(operator.label),
            }))}
        />
    );
}

function SwitchForm({ data, set, options }: FormProps) {
    const { t } = useTranslation();
    const cases = Array.isArray(data.cases)
        ? (data.cases as { id: string; value: string }[])
        : [];

    return (
        <>
            <Field label={t('Compare')}>
                <FieldSelect
                    fields={workflowFields(options)}
                    value={str(data.field)}
                    onChange={(field) => set({ field })}
                />
            </Field>
            <Field
                label={t('Cases')}
                hint={t(
                    'The first case equal to the value is followed; otherwise “default”.',
                )}
            >
                <div className="space-y-1.5">
                    {cases.map((item, index) => (
                        <div key={item.id} className="flex gap-1.5">
                            <Input
                                value={item.value}
                                placeholder={t('Value')}
                                onChange={(event) =>
                                    set({
                                        cases: cases.map((current, position) =>
                                            position === index
                                                ? {
                                                      ...current,
                                                      value: event.target.value,
                                                  }
                                                : current,
                                        ),
                                    })
                                }
                            />
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                title={t('Remove')}
                                onClick={() =>
                                    set({
                                        cases: cases.filter(
                                            (_, position) => position !== index,
                                        ),
                                    })
                                }
                            >
                                <X className="size-3.5" />
                            </Button>
                        </div>
                    ))}
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        onClick={() => {
                            const next =
                                Math.max(
                                    0,
                                    ...cases.map(
                                        (item) =>
                                            Number(
                                                item.id.replace(/\D/g, ''),
                                            ) || 0,
                                    ),
                                ) + 1;
                            set({
                                cases: [
                                    ...cases,
                                    { id: `c${next}`, value: '' },
                                ],
                            });
                        }}
                    >
                        <Plus /> {t('Case')}
                    </Button>
                </div>
            </Field>
        </>
    );
}

function ForEachForm({ data, set, options }: FormProps) {
    const { t } = useTranslation();
    const collection = str(data.collection);
    const filters = (data.filters as Record<string, string> | undefined) ?? {};
    const setFilter = (key: string, value: string) =>
        set({ filters: { ...filters, [key]: value } });

    return (
        <>
            <Field label={t('Loop over')}>
                <OptionSelect
                    label={t('Loop over')}
                    value={collection}
                    options={Object.entries(COLLECTION_LABELS).map(
                        ([value, label]) => ({ value, label: t(label) }),
                    )}
                    onChange={(next) => set({ collection: next })}
                />
            </Field>
            {collection === 'find_tickets' && (
                <div className="grid gap-2 rounded-lg border p-2">
                    <OptionSelect
                        label={t('Status')}
                        emptyLabel={t('Any status')}
                        value={filters.status ?? ''}
                        options={options.statuses}
                        onChange={(value) => setFilter('status', value)}
                    />
                    <OptionSelect
                        label={t('Priority')}
                        emptyLabel={t('Any priority')}
                        value={filters.priority ?? ''}
                        options={options.priorities}
                        onChange={(value) => setFilter('priority', value)}
                    />
                    <OptionSelect
                        label={t('Group')}
                        emptyLabel={t('Any group')}
                        value={filters.group_id ?? ''}
                        options={options.groups.map((group) => ({
                            value: String(group.id),
                            label: group.name,
                        }))}
                        onChange={(value) => setFilter('group_id', value)}
                    />
                    <Input
                        value={filters.tag ?? ''}
                        placeholder={t('Tag')}
                        onChange={(event) =>
                            setFilter('tag', event.target.value)
                        }
                    />
                    <Input
                        value={filters.search ?? ''}
                        placeholder={t('Search, e.g. {{requester.email}}')}
                        onChange={(event) =>
                            setFilter('search', event.target.value)
                        }
                    />
                </div>
            )}
            {TICKET_COLLECTIONS.includes(collection) &&
                collection !== 'find_tickets' && (
                    <Field
                        label={t('Only with status')}
                        hint={t('Leave empty for any status.')}
                    >
                        <MultiSelectChips
                            options={options.statuses.map((status) => ({
                                value: String(status.value),
                                label: status.label,
                            }))}
                            value={strings(data.statuses)}
                            onChange={(statuses) => set({ statuses })}
                        />
                    </Field>
                )}
            {collection === 'variable' && (
                <Field
                    label={t('Variable')}
                    hint={t('A list, e.g. “tags” or “crm.body.items”.')}
                >
                    <Input
                        className="font-mono text-xs"
                        value={str(data.variable)}
                        onChange={(event) =>
                            set({ variable: event.target.value.trim() })
                        }
                    />
                </Field>
            )}
            <Field label={t('At most')}>
                <Input
                    type="number"
                    min={1}
                    max={100}
                    value={str(data.limit) || '100'}
                    onChange={(event) =>
                        set({ limit: Number(event.target.value) })
                    }
                />
            </Field>
            <p className="rounded-lg bg-muted px-3 py-2 text-xs text-muted-foreground">
                {t(
                    'Steps connected to “each” run once per item and can use {{item.…}}, {{loop.index}} and {{loop.count}}. “done” continues after the last item.',
                )}
            </p>
        </>
    );
}

function SetVariableForm({ data, set, variables }: FormProps) {
    const { t } = useTranslation();

    return (
        <>
            <Field
                label={t('Name')}
                hint={t('Use it later as {{vars.:name}}.', {
                    name: str(data.name) || 'name',
                })}
            >
                <Input
                    className="font-mono text-xs"
                    value={str(data.name)}
                    onChange={(event) =>
                        set({
                            name: event.target.value.replace(
                                /[^a-z0-9_]/gi,
                                '',
                            ),
                        })
                    }
                />
            </Field>
            <Field label={t('Kind')}>
                <OptionSelect
                    label={t('Kind')}
                    value={str(data.kind) || 'text'}
                    options={[
                        { value: 'text', label: t('Text') },
                        { value: 'number', label: t('Number') },
                        { value: 'list', label: t('List (comma separated)') },
                    ]}
                    onChange={(kind) => set({ kind })}
                />
            </Field>
            <TemplateInput
                label={t('Value')}
                value={str(data.value)}
                onChange={(value) => set({ value })}
                variables={variables}
            />
        </>
    );
}

function UpdateTicketForm({ data, set, options }: FormProps) {
    const { t } = useTranslation();
    const keep = t("Don't change");
    const customFields =
        (data.custom_fields as Record<string, string> | undefined) ?? {};
    const categories = options.categories.flatMap((category) => [
        { value: String(category.id), label: category.name },
        ...(category.children ?? []).map((child) => ({
            value: String(child.id),
            label: `${category.name} › ${child.name}`,
        })),
    ]);

    return (
        <>
            <Field label={t('Status')}>
                <OptionSelect
                    label={t('Status')}
                    emptyLabel={keep}
                    value={
                        str(data.ticket_status_id) ||
                        String(
                            options.customStatuses.find(
                                (status) =>
                                    status.is_default &&
                                    status.category === str(data.status),
                            )?.id ?? '',
                        )
                    }
                    options={customStatusChoices(options)}
                    onChange={(ticket_status_id) =>
                        set({ ticket_status_id, status: '' })
                    }
                />
            </Field>
            <Field label={t('Priority')}>
                <OptionSelect
                    label={t('Priority')}
                    emptyLabel={keep}
                    value={str(data.priority)}
                    options={options.priorities}
                    onChange={(priority) => set({ priority })}
                />
            </Field>
            <Field label={t('Type')}>
                <OptionSelect
                    label={t('Type')}
                    emptyLabel={keep}
                    value={str(data.type)}
                    options={options.types}
                    onChange={(type) => set({ type })}
                />
            </Field>
            <Field label={t('Group')}>
                <OptionSelect
                    label={t('Group')}
                    emptyLabel={keep}
                    value={str(data.group_id)}
                    options={options.groups.map((group) => ({
                        value: String(group.id),
                        label: group.name,
                    }))}
                    onChange={(group_id) => set({ group_id })}
                />
            </Field>
            <Field label={t('Assignee')}>
                <OptionSelect
                    label={t('Assignee')}
                    emptyLabel={keep}
                    value={str(data.assignee_id)}
                    options={[
                        { value: 'none', label: t('Unassigned') },
                        ...options.agents.map((agent) => ({
                            value: String(agent.id),
                            label: agent.name,
                        })),
                    ]}
                    onChange={(assignee_id) => set({ assignee_id })}
                />
            </Field>
            <Field label={t('Category')}>
                <OptionSelect
                    label={t('Category')}
                    emptyLabel={keep}
                    value={str(data.category_id)}
                    options={categories}
                    onChange={(category_id) => set({ category_id })}
                />
            </Field>
            {options.fields.length > 0 && (
                <Field
                    label={t('Custom fields')}
                    hint={t('Empty fields are left as they are.')}
                >
                    <div className="grid gap-1.5">
                        {options.fields.map((field) => (
                            <Input
                                key={field.key}
                                aria-label={field.label}
                                placeholder={field.label}
                                value={customFields[field.key] ?? ''}
                                onChange={(event) =>
                                    set({
                                        custom_fields: {
                                            ...customFields,
                                            [field.key]: event.target.value,
                                        },
                                    })
                                }
                            />
                        ))}
                    </div>
                </Field>
            )}
        </>
    );
}

function SendEmailForm(props: FormProps) {
    const { t } = useTranslation();
    const { data, set, variables } = props;

    return (
        <>
            <Field label={t('To')}>
                <OptionSelect
                    label={t('To')}
                    value={str(data.to)}
                    options={[
                        { value: 'requester', label: t('Requester') },
                        { value: 'assignee', label: t('Assignee') },
                        { value: 'group', label: t("The ticket's group") },
                        { value: 'address', label: t('These addresses') },
                    ]}
                    onChange={(to) => set({ to })}
                />
            </Field>
            {data.to === 'address' && (
                <TemplateInput
                    label={t('Addresses')}
                    value={str(data.address)}
                    onChange={(address) => set({ address })}
                    variables={variables}
                    placeholder="ops@example.com"
                    hint={t('Separate several with commas (at most 10).')}
                />
            )}
            <TemplateInput
                label={t('Subject')}
                value={str(data.subject)}
                onChange={(subject) => set({ subject })}
                variables={variables}
            />
            <RichBody {...props} label={t('Message')} />
        </>
    );
}

function NotifyForm({ data, set, options, variables }: FormProps) {
    const { t } = useTranslation();

    return (
        <>
            <Field label={t('Who')}>
                <OptionSelect
                    label={t('Who')}
                    value={str(data.to)}
                    options={[
                        { value: 'assignee', label: t('Assignee') },
                        { value: 'group', label: t("The ticket's group") },
                        { value: 'admins', label: t('Administrators') },
                        { value: 'users', label: t('Chosen people') },
                    ]}
                    onChange={(to) => set({ to })}
                />
            </Field>
            {data.to === 'users' && (
                <MultiSelectChips
                    options={options.agents.map((agent) => ({
                        value: agent.id,
                        label: agent.name,
                    }))}
                    value={(Array.isArray(data.user_ids)
                        ? data.user_ids
                        : []
                    ).map(Number)}
                    onChange={(user_ids) => set({ user_ids })}
                />
            )}
            <TemplateTextarea
                label={t('Message')}
                value={str(data.message)}
                onChange={(message) => set({ message })}
                variables={variables}
                rows={3}
            />
        </>
    );
}

type AiField = { enabled?: boolean; apply?: boolean; options?: string[] };

const AI_APPLICABLE = ['category', 'priority', 'type', 'tags'];

const AI_FIELD_HINTS: Record<string, string> = {
    category:
        'One of the active categories (subcategories when a category has them).',
    priority: 'Low, normal, high or urgent.',
    type: 'Question, incident, problem or task.',
    tags: 'Any of the tags you list.',
    sentiment: 'Positive, neutral, negative or angry.',
    language: 'A two-letter code such as en, pt or es.',
    intent: 'One of the intents you list, e.g. refund, bug, sales.',
};

function AiClassifyForm({ data, set }: FormProps) {
    const { t } = useTranslation();
    const fields = (data.fields ?? {}) as Record<string, AiField>;
    const saveAs = str(data.save_as) || 'ai';
    const setField = (key: string, patch: AiField) =>
        set({ fields: { ...fields, [key]: { ...fields[key], ...patch } } });

    return (
        <>
            <div className="space-y-3">
                {Object.keys(AI_FIELD_LABELS).map((key) => {
                    const field = fields[key] ?? {};

                    return (
                        <div key={key} className="space-y-1.5">
                            <div className="flex items-center gap-3">
                                <label className="flex flex-1 items-center gap-2 text-sm font-medium">
                                    <Checkbox
                                        checked={Boolean(field.enabled)}
                                        onCheckedChange={(checked) =>
                                            setField(key, {
                                                enabled: checked === true,
                                            })
                                        }
                                    />
                                    {t(AI_FIELD_LABELS[key])}
                                </label>
                                {AI_APPLICABLE.includes(key) &&
                                    field.enabled && (
                                        <label className="flex items-center gap-1.5 text-xs text-muted-foreground">
                                            <Checkbox
                                                checked={Boolean(field.apply)}
                                                onCheckedChange={(checked) =>
                                                    setField(key, {
                                                        apply: checked === true,
                                                    })
                                                }
                                            />
                                            {t('Apply to ticket')}
                                        </label>
                                    )}
                            </div>
                            {field.enabled && (
                                <p className="pl-6 text-xs text-muted-foreground">
                                    {t(AI_FIELD_HINTS[key])}
                                </p>
                            )}
                            {field.enabled &&
                                (key === 'tags' || key === 'intent') && (
                                    <div className="pl-6">
                                        <ListInput
                                            label={t(AI_FIELD_LABELS[key])}
                                            value={strings(field.options)}
                                            onChange={(options) =>
                                                setField(key, { options })
                                            }
                                            placeholder={
                                                key === 'tags'
                                                    ? 'vip, billing, outage'
                                                    : 'refund, bug, sales, how_to'
                                            }
                                        />
                                    </div>
                                )}
                        </div>
                    );
                })}
            </div>
            <Field
                label={t('Minimum confidence')}
                hint={t(
                    'Answers below it aren’t applied, and the run continues from “unsure”.',
                )}
            >
                <div className="flex items-center gap-2">
                    <Input
                        type="number"
                        min={0}
                        max={100}
                        className="w-24"
                        value={str(data.min_confidence)}
                        onChange={(event) =>
                            set({ min_confidence: Number(event.target.value) })
                        }
                    />
                    <span className="text-sm text-muted-foreground">%</span>
                </div>
            </Field>
            <label className="flex items-start gap-2 text-sm">
                <Checkbox
                    className="mt-0.5"
                    checked={data.keep_existing !== false}
                    onCheckedChange={(checked) =>
                        set({ keep_existing: checked === true })
                    }
                />
                {t(
                    'Keep the category and type when the ticket already has them',
                )}
            </label>
            <Field
                label={t('Save the results as')}
                hint={t(
                    'Then use {{vars.:name.category}}, {{vars.:name.intent}}, {{vars.:name.confidence.priority}}… in later steps.',
                    { name: saveAs },
                )}
            >
                <Input
                    className="font-mono text-xs"
                    value={str(data.save_as)}
                    onChange={(event) =>
                        set({
                            save_as: event.target.value.replace(
                                /[^a-z0-9_]/gi,
                                '',
                            ),
                        })
                    }
                />
            </Field>
            <p className="text-xs text-muted-foreground">
                {t(
                    'Test runs ask the AI but don’t change the ticket. Setting the category re-runs the routing rules.',
                )}
            </p>
        </>
    );
}

function AiPromptForm({ data, set, variables }: FormProps) {
    const { t } = useTranslation();

    return (
        <>
            <TemplateTextarea
                label={t('Prompt')}
                value={str(data.prompt)}
                onChange={(prompt) => set({ prompt })}
                variables={variables}
                rows={6}
            />
            <p className="text-xs text-muted-foreground">
                {t(
                    'e.g. “Extract the order number from the customer’s messages. Reply with the number only, or NONE.”',
                )}
            </p>
            <label className="flex items-center gap-2 text-sm">
                <Checkbox
                    checked={data.include_conversation !== false}
                    onCheckedChange={(checked) =>
                        set({ include_conversation: checked === true })
                    }
                />
                {t('Include the ticket and its conversation')}
            </label>
            <Field
                label={t('Save the answer as')}
                hint={t('Use it later as {{vars.:name}}.', {
                    name: str(data.save_as) || 'answer',
                })}
            >
                <Input
                    className="font-mono text-xs"
                    value={str(data.save_as)}
                    onChange={(event) =>
                        set({
                            save_as: event.target.value.replace(
                                /[^a-z0-9_]/gi,
                                '',
                            ),
                        })
                    }
                />
            </Field>
        </>
    );
}

function AiDraftNoteForm({ data, set }: FormProps) {
    const { t } = useTranslation();

    return (
        <>
            <p className="text-sm text-muted-foreground">
                {t(
                    'Adds a suggested reply as an internal note for the agent to review. It is never sent to the customer. Test runs don’t call the AI.',
                )}
            </p>
            <label className="flex items-center gap-2 text-sm">
                <Checkbox
                    checked={data.use_articles !== false}
                    onCheckedChange={(checked) =>
                        set({ use_articles: checked === true })
                    }
                />
                {t('Use related help center articles')}
            </label>
            <Field label={t('Instructions (optional)')}>
                <Input
                    value={str(data.instruction)}
                    maxLength={500}
                    onChange={(event) =>
                        set({ instruction: event.target.value })
                    }
                    placeholder={t(
                        'e.g. Offer a refund and apologize for the delay.',
                    )}
                />
            </Field>
        </>
    );
}

function HttpRequestForm({ data, set, variables }: FormProps) {
    const { t } = useTranslation();
    const headers = Array.isArray(data.headers)
        ? (data.headers as { name: string; value: string }[])
        : [];

    return (
        <>
            <div className="grid grid-cols-[6rem_1fr] gap-2">
                <Field label={t('Method')}>
                    <OptionSelect
                        label={t('Method')}
                        value={str(data.method)}
                        options={['GET', 'POST', 'PUT', 'PATCH', 'DELETE'].map(
                            (method) => ({ value: method, label: method }),
                        )}
                        onChange={(method) => set({ method })}
                    />
                </Field>
                <TemplateInput
                    label={t('URL')}
                    value={str(data.url)}
                    onChange={(url) => set({ url })}
                    variables={variables}
                />
            </div>
            <Field label={t('Headers')}>
                <div className="space-y-1.5">
                    {headers.map((header, index) => (
                        <div key={index} className="flex gap-1.5">
                            <Input
                                className="font-mono text-xs"
                                placeholder="X-Api-Key"
                                value={header.name}
                                onChange={(event) =>
                                    set({
                                        headers: headers.map(
                                            (item, position) =>
                                                position === index
                                                    ? {
                                                          ...item,
                                                          name: event.target
                                                              .value,
                                                      }
                                                    : item,
                                        ),
                                    })
                                }
                            />
                            <Input
                                className="font-mono text-xs"
                                value={header.value}
                                onChange={(event) =>
                                    set({
                                        headers: headers.map(
                                            (item, position) =>
                                                position === index
                                                    ? {
                                                          ...item,
                                                          value: event.target
                                                              .value,
                                                      }
                                                    : item,
                                        ),
                                    })
                                }
                            />
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                title={t('Remove')}
                                onClick={() =>
                                    set({
                                        headers: headers.filter(
                                            (_, position) => position !== index,
                                        ),
                                    })
                                }
                            >
                                <X className="size-3.5" />
                            </Button>
                        </div>
                    ))}
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        onClick={() =>
                            set({
                                headers: [...headers, { name: '', value: '' }],
                            })
                        }
                    >
                        <Plus /> {t('Header')}
                    </Button>
                </div>
            </Field>
            {data.method !== 'GET' && (
                <TemplateTextarea
                    label={t('Body')}
                    value={str(data.body)}
                    onChange={(body) => set({ body })}
                    variables={variables}
                    rows={5}
                    mono
                />
            )}
            <Field
                label={t('Save the response as')}
                hint={t(
                    'Then use {{vars.:name.status}} and {{vars.:name.body.…}}.',
                    { name: str(data.save_as) || 'response' },
                )}
            >
                <Input
                    className="font-mono text-xs"
                    placeholder="response"
                    value={str(data.save_as)}
                    onChange={(event) =>
                        set({
                            save_as: event.target.value.replace(
                                /[^a-z0-9_]/gi,
                                '',
                            ),
                        })
                    }
                />
            </Field>
            <p className="text-xs text-muted-foreground">
                {t(
                    'Only public internet addresses can be called, and redirects are not followed.',
                )}
            </p>
        </>
    );
}

function NodeForm(props: FormProps & { type: WorkflowNodeType }) {
    const { t } = useTranslation();
    const { type, data, set, options, variables } = props;

    switch (type) {
        case 'trigger':
            return <TriggerForm {...props} />;
        case 'if':
        case 'filter':
            return (
                <>
                    {type === 'filter' && (
                        <p className="text-xs text-muted-foreground">
                            {t(
                                'Inside a loop, items that don’t match are skipped. Elsewhere, the run stops.',
                            )}
                        </p>
                    )}
                    <ConditionsForm {...props} />
                </>
            );
        case 'switch':
            return <SwitchForm {...props} />;
        case 'for_each':
            return <ForEachForm {...props} />;
        case 'set_variable':
            return <SetVariableForm {...props} />;
        case 'wait':
            return (
                <>
                    <Duration {...props} />
                    <label className="flex items-center gap-2 text-sm">
                        <Checkbox
                            checked={Boolean(data.business_hours)}
                            onCheckedChange={(checked) =>
                                set({ business_hours: checked === true })
                            }
                        />
                        {t("Count only the ticket's business hours")}
                    </label>
                </>
            );
        case 'wait_for_reply':
            return (
                <>
                    <Duration {...props} />
                    <p className="text-xs text-muted-foreground">
                        {t(
                            'Continues from “replied” as soon as the customer answers, or from “timeout” when the time is up.',
                        )}
                    </p>
                </>
            );
        case 'stop':
            return (
                <Field label={t('Stop')}>
                    <OptionSelect
                        label={t('Stop')}
                        value={str(data.scope) || 'run'}
                        options={[
                            { value: 'run', label: t('End the run') },
                            {
                                value: 'loop',
                                label: t(
                                    'Leave the loop and continue from “done”',
                                ),
                            },
                        ]}
                        onChange={(scope) => set({ scope })}
                    />
                </Field>
            );
        case 'update_ticket':
            return <UpdateTicketForm {...props} />;
        case 'add_tags':
        case 'remove_tags':
            return (
                <Field label={t('Tags')} hint={t('Separate tags with commas.')}>
                    <ListInput
                        label={t('Tags')}
                        value={strings(data.tags)}
                        onChange={(tags) => set({ tags })}
                        placeholder="vip, follow_up"
                    />
                </Field>
            );
        case 'auto_assign':
            return (
                <p className="text-sm text-muted-foreground">
                    {t(
                        "Picks an available agent of the ticket's group with the group's assignment mode (round robin or least busy). Tickets that already have an assignee are left alone.",
                    )}
                </p>
            );
        case 'add_note':
            return <RichBody {...props} label={t('Note')} />;
        case 'reply':
            return (
                <>
                    <RichBody {...props} label={t('Reply')} />
                    <Field label={t('Then set status to')}>
                        <OptionSelect
                            label={t('Then set status to')}
                            emptyLabel={t('Keep the usual status')}
                            value={str(data.status_after)}
                            options={options.statuses}
                            onChange={(status_after) => set({ status_after })}
                        />
                    </Field>
                </>
            );
        case 'send_email':
            return <SendEmailForm {...props} />;
        case 'notify':
            return <NotifyForm {...props} />;
        case 'add_cc':
            return (
                <Field
                    label={t('Email addresses')}
                    hint={t(
                        'Separate addresses with commas. New addresses get a customer account.',
                    )}
                >
                    <ListInput
                        label={t('Email addresses')}
                        value={strings(data.emails)}
                        onChange={(emails) => set({ emails })}
                    />
                </Field>
            );
        case 'http_request':
            return <HttpRequestForm {...props} />;
        case 'ai_classify':
            return <AiClassifyForm {...props} />;
        case 'ai_prompt':
            return <AiPromptForm {...props} />;
        case 'ai_summary_note':
            return (
                <p className="text-sm text-muted-foreground">
                    {t(
                        'Adds an internal note with a short AI summary of the ticket: what the customer needs, what was done, and the next step. Test runs don’t call the AI.',
                    )}
                </p>
            );
        case 'ai_draft_note':
            return <AiDraftNoteForm {...props} />;
        default:
            return (
                <p className="text-sm text-muted-foreground">
                    {variables.join(', ')}
                </p>
            );
    }
}

/**
 * The settings panel for the selected step.
 */
export function NodeInspector({
    nodeId,
    type,
    data,
    options,
    variables,
    inLoop,
    error,
    step,
    readOnly,
    onChange,
    onDelete,
    onClose,
}: {
    nodeId: string;
    type: WorkflowNodeType;
    data: WorkflowNodeData;
    options: WorkflowOptions;
    variables: string[];
    inLoop: boolean;
    error?: string;
    step?: WorkflowStep;
    readOnly: boolean;
    onChange: (data: WorkflowNodeData) => void;
    onDelete: () => void;
    onClose: () => void;
}) {
    const { t } = useTranslation();
    const definition = NODE_DEFINITIONS[type];
    const set = (patch: WorkflowNodeData) => onChange({ ...data, ...patch });
    const Icon = definition.icon;

    let body: ReactNode = (
        <NodeForm
            type={type}
            data={data}
            set={set}
            options={options}
            variables={variables}
        />
    );

    if (TICKET_ACTIONS.includes(type) && (inLoop || data.apply_to === 'item')) {
        body = (
            <>
                <Field
                    label={t('Apply to')}
                    hint={t(
                        'Inside a loop over tickets, act on the ticket being looped over.',
                    )}
                >
                    <OptionSelect
                        label={t('Apply to')}
                        value={str(data.apply_to) || 'trigger'}
                        options={[
                            {
                                value: 'trigger',
                                label: t(
                                    'The ticket that started the workflow',
                                ),
                            },
                            {
                                value: 'item',
                                label: t('The current loop item'),
                            },
                        ]}
                        onChange={(apply_to) => set({ apply_to })}
                    />
                </Field>
                {body}
            </>
        );
    }

    return (
        <div key={nodeId} className="flex min-h-0 flex-1 flex-col">
            <div className="flex items-start gap-3 border-b p-4">
                <span
                    className={cn(
                        'flex size-9 shrink-0 items-center justify-center rounded-lg',
                        CATEGORY_STYLES[definition.category],
                    )}
                >
                    <Icon className="size-4.5" />
                </span>
                <div className="min-w-0 flex-1">
                    <h2 className="font-medium">{t(definition.label)}</h2>
                    <p className="text-xs text-muted-foreground">
                        {t(definition.description)}
                    </p>
                </div>
                {type !== 'trigger' && !readOnly && (
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        title={t('Delete step')}
                        onClick={onDelete}
                    >
                        <Trash2 />
                    </Button>
                )}
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    title={t('Close')}
                    onClick={onClose}
                >
                    <X />
                </Button>
            </div>

            <div className={cn('min-h-0 flex-1 space-y-4 overflow-y-auto p-4')}>
                {error && (
                    <p className="flex items-start gap-2 rounded-lg bg-destructive/10 px-3 py-2 text-sm text-destructive">
                        <AlertCircle className="mt-0.5 size-4 shrink-0" />
                        {error}
                    </p>
                )}
                <fieldset
                    disabled={readOnly}
                    className={cn(
                        'min-w-0 space-y-4',
                        readOnly && 'opacity-70',
                    )}
                >
                    {body}
                </fieldset>
            </div>

            {step && (
                <div className="max-h-56 overflow-y-auto border-t bg-muted/40 p-4">
                    <p className="mb-1 text-xs font-medium text-muted-foreground">
                        {t('Last result')}
                        {step.iteration !== null &&
                            ` · ${t('iteration :count', { count: step.iteration })}`}
                    </p>
                    {step.error ? (
                        <p className="text-xs text-destructive">{step.error}</p>
                    ) : (
                        <pre className="text-xs break-all whitespace-pre-wrap">
                            {JSON.stringify(step.output, null, 2)}
                        </pre>
                    )}
                </div>
            )}
        </div>
    );
}
