import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { t } from '@/lib/i18n';
import type { CategoryNode, NamedRecord, Option, TicketField } from '@/types';

export type RoutingCondition = {
    field: string;
    operator: 'is' | 'is_not' | 'contains';
    value: string;
};

export type RoutingRule = {
    id: number;
    name: string;
    is_active: boolean;
    match: 'all' | 'any';
    conditions: RoutingCondition[];
    actions: { group_id: number; priority: string | null; tags: string[] };
};

export type RoutingOptions = {
    operators: Option[];
    categories: CategoryNode[];
    groups: NamedRecord[];
    organizations: NamedRecord[];
    priorities: Option[];
    types: Option[];
    channels: Option[];
    fields: TicketField[];
};

type FieldDefinition = {
    value: string;
    label: string;
    /** Choices for the value; free text when null. */
    choices: Option[] | null;
};

/**
 * Ticket properties a routing condition can test.
 */
export function conditionFields(options: RoutingOptions): FieldDefinition[] {
    const categories = options.categories.flatMap((category) => [
        { value: String(category.id), label: category.name },
        ...(category.children ?? []).map((child) => ({
            value: String(child.id),
            label: `${category.name} › ${child.name}`,
        })),
    ]);
    const records = (items: NamedRecord[]) =>
        items.map((item) => ({ value: String(item.id), label: item.name }));

    return [
        { value: 'category', label: t('Category'), choices: categories },
        {
            value: 'priority',
            label: t('Priority'),
            choices: options.priorities,
        },
        { value: 'type', label: t('Type'), choices: options.types },
        { value: 'channel', label: t('Channel'), choices: options.channels },
        {
            value: 'organization',
            label: t('Organization'),
            choices: records(options.organizations),
        },
        {
            value: 'requester_email',
            label: t('Requester email'),
            choices: null,
        },
        ...options.fields.map((field) => ({
            value: `custom_fields.${field.key}`,
            label: field.label,
            choices:
                field.type === 'select'
                    ? (field.options ?? []).map((option) => ({
                          value: option,
                          label: option,
                      }))
                    : field.type === 'checkbox'
                      ? [
                            { value: '1', label: t('Checked') },
                            { value: '0', label: t('Not checked') },
                        ]
                      : null,
        })),
    ];
}

export function findField(
    options: RoutingOptions,
    field: string,
): FieldDefinition | undefined {
    return conditionFields(options).find(
        (definition) => definition.value === field,
    );
}

/**
 * "Category is Billing › Refunds" for rule summaries.
 */
export function describeCondition(
    condition: RoutingCondition,
    options: RoutingOptions,
): string {
    const field = findField(options, condition.field);
    const operator =
        options.operators.find((option) => option.value === condition.operator)
            ?.label ?? condition.operator;
    const value =
        field?.choices?.find((choice) => choice.value === condition.value)
            ?.label ?? condition.value;

    return `${field?.label ?? condition.field} ${operator} ${value}`;
}

/**
 * The value input for a condition: a select for fields with choices, else text.
 */
export function ConditionValueInput({
    field,
    value,
    onChange,
}: {
    field: FieldDefinition | undefined;
    value: string;
    onChange: (value: string) => void;
}) {
    if (field?.choices) {
        return (
            <Select value={value} onValueChange={onChange}>
                <SelectTrigger className="w-full" aria-label={t('Value')}>
                    <SelectValue placeholder={t('Select…')} />
                </SelectTrigger>
                <SelectContent>
                    {field.choices.map((choice) => (
                        <SelectItem key={choice.value} value={choice.value}>
                            {choice.label}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
        );
    }

    return (
        <Input
            aria-label={t('Value')}
            value={value}
            onChange={(event) => onChange(event.target.value)}
        />
    );
}
