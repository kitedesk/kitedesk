import { Plus, Trash2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectLabel,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { isGroup } from '@/components/workflows/node-catalog';
import type { WorkflowField } from '@/components/workflows/workflow-fields';
import { findWorkflowField } from '@/components/workflows/workflow-fields';
import { useTranslation } from '@/hooks/use-translation';
import { cn } from '@/lib/utils';
import type { Condition, ConditionGroup, Option } from '@/types';

const MAX_DEPTH = 3;

const UNARY = ['is_empty', 'is_set'];

const CUSTOM = { item: '__item', vars: '__vars' };

/**
 * Pick what a condition tests: a ticket field, a property of the loop item, or a variable.
 */
export function FieldSelect({
    fields,
    value,
    onChange,
}: {
    fields: WorkflowField[];
    value: string;
    onChange: (value: string) => void;
}) {
    const { t } = useTranslation();
    const mode =
        value === 'item' || value.startsWith('item.')
            ? CUSTOM.item
            : value.startsWith('vars.')
              ? CUSTOM.vars
              : value;
    const groups = [...new Set(fields.map((field) => field.group))];

    return (
        <div className="flex min-w-0 flex-1 gap-1.5">
            <Select
                value={mode}
                onValueChange={(next) =>
                    onChange(
                        next === CUSTOM.item
                            ? 'item.'
                            : next === CUSTOM.vars
                              ? 'vars.'
                              : next,
                    )
                }
            >
                <SelectTrigger
                    className="min-w-0 flex-1"
                    aria-label={t('Field')}
                >
                    <SelectValue placeholder={t('Field…')} />
                </SelectTrigger>
                <SelectContent>
                    {groups.map((group) => (
                        <SelectGroup key={group}>
                            <SelectLabel>{t(group)}</SelectLabel>
                            {fields
                                .filter((field) => field.group === group)
                                .map((field) => (
                                    <SelectItem
                                        key={field.value}
                                        value={field.value}
                                    >
                                        {t(field.label)}
                                    </SelectItem>
                                ))}
                        </SelectGroup>
                    ))}
                    <SelectGroup>
                        <SelectLabel>{t('Workflow')}</SelectLabel>
                        <SelectItem value={CUSTOM.item}>
                            {t('Loop item…')}
                        </SelectItem>
                        <SelectItem value={CUSTOM.vars}>
                            {t('Variable…')}
                        </SelectItem>
                    </SelectGroup>
                </SelectContent>
            </Select>
            {(mode === CUSTOM.item || mode === CUSTOM.vars) && (
                <Input
                    aria-label={t('Path')}
                    className="min-w-0 flex-1 font-mono text-xs"
                    value={value}
                    placeholder={
                        mode === CUSTOM.item ? 'item.status' : 'vars.name'
                    }
                    onChange={(event) => onChange(event.target.value.trim())}
                />
            )}
        </div>
    );
}

/**
 * The value to compare with: a choice for fields with options, else text (placeholders allowed).
 */
export function ValueInput({
    field,
    value,
    onChange,
}: {
    field: WorkflowField | undefined;
    value: string;
    onChange: (value: string) => void;
}) {
    const { t } = useTranslation();

    if (field?.choices && !value.includes('{{')) {
        return (
            <Select value={value} onValueChange={onChange}>
                <SelectTrigger
                    className="min-w-0 flex-1"
                    aria-label={t('Value')}
                >
                    <SelectValue placeholder={t('Select…')} />
                </SelectTrigger>
                <SelectContent>
                    {field.choices.map((choice) => (
                        <SelectItem
                            key={choice.value}
                            value={String(choice.value)}
                        >
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
            className="min-w-0 flex-1"
            value={value}
            placeholder={t('Value or {{placeholder}}')}
            onChange={(event) => onChange(event.target.value)}
        />
    );
}

function ConditionRow({
    condition,
    fields,
    operators,
    onChange,
    onRemove,
}: {
    condition: Condition;
    fields: WorkflowField[];
    operators: Option[];
    onChange: (condition: Condition) => void;
    onRemove: () => void;
}) {
    const { t } = useTranslation();
    const field = findWorkflowField(fields, condition.field);

    return (
        <div className="space-y-1.5 rounded-lg border bg-background p-2">
            <div className="flex items-center gap-1.5">
                <FieldSelect
                    fields={fields}
                    value={condition.field}
                    onChange={(value) =>
                        onChange({ ...condition, field: value, value: '' })
                    }
                />
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    className="size-8 shrink-0"
                    title={t('Remove')}
                    onClick={onRemove}
                >
                    <Trash2 className="size-3.5" />
                </Button>
            </div>
            <div className="flex gap-1.5">
                <Select
                    value={condition.operator}
                    onValueChange={(operator) =>
                        onChange({ ...condition, operator })
                    }
                >
                    <SelectTrigger
                        className="w-40 shrink-0"
                        aria-label={t('Operator')}
                    >
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        {operators.map((operator) => (
                            <SelectItem
                                key={operator.value}
                                value={String(operator.value)}
                            >
                                {operator.label}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                {!UNARY.includes(condition.operator) && (
                    <ValueInput
                        field={
                            condition.operator === 'matches' ? undefined : field
                        }
                        value={condition.value}
                        onChange={(value) => onChange({ ...condition, value })}
                    />
                )}
            </div>
        </div>
    );
}

/**
 * Nested AND/OR condition groups.
 */
export function ConditionBuilder({
    value,
    onChange,
    fields,
    operators,
    depth = 1,
    onRemove,
}: {
    value: ConditionGroup;
    onChange: (value: ConditionGroup) => void;
    fields: WorkflowField[];
    operators: Option[];
    depth?: number;
    onRemove?: () => void;
}) {
    const { t } = useTranslation();

    const update = (index: number, item: Condition | ConditionGroup) =>
        onChange({
            ...value,
            conditions: value.conditions.map((current, position) =>
                position === index ? item : current,
            ),
        });

    const remove = (index: number) =>
        onChange({
            ...value,
            conditions: value.conditions.filter(
                (_, position) => position !== index,
            ),
        });

    return (
        <div
            className={cn(
                'space-y-2',
                depth > 1 && 'rounded-lg border border-dashed bg-muted/40 p-2',
            )}
        >
            <div className="flex items-center gap-2 text-xs text-muted-foreground">
                <span>{t('Match')}</span>
                <Select
                    value={value.match}
                    onValueChange={(match) =>
                        onChange({ ...value, match: match as 'all' | 'any' })
                    }
                >
                    <SelectTrigger
                        className="h-7 w-24 text-xs"
                        aria-label={t('Match')}
                    >
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">{t('all')}</SelectItem>
                        <SelectItem value="any">{t('any')}</SelectItem>
                    </SelectContent>
                </Select>
                <span className="flex-1">{t('of these conditions')}</span>
                {onRemove && (
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        className="size-7"
                        title={t('Remove group')}
                        onClick={onRemove}
                    >
                        <Trash2 className="size-3.5" />
                    </Button>
                )}
            </div>

            {value.conditions.map((item, index) =>
                isGroup(item) ? (
                    <ConditionBuilder
                        key={index}
                        value={item}
                        onChange={(group) => update(index, group)}
                        onRemove={() => remove(index)}
                        fields={fields}
                        operators={operators}
                        depth={depth + 1}
                    />
                ) : (
                    <ConditionRow
                        key={index}
                        condition={item}
                        fields={fields}
                        operators={operators}
                        onChange={(condition) => update(index, condition)}
                        onRemove={() => remove(index)}
                    />
                ),
            )}

            <div className="flex gap-2">
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    onClick={() =>
                        onChange({
                            ...value,
                            conditions: [
                                ...value.conditions,
                                {
                                    field: 'subject',
                                    operator: 'contains',
                                    value: '',
                                },
                            ],
                        })
                    }
                >
                    <Plus /> {t('Condition')}
                </Button>
                {depth < MAX_DEPTH && (
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        onClick={() =>
                            onChange({
                                ...value,
                                conditions: [
                                    ...value.conditions,
                                    {
                                        match:
                                            value.match === 'all'
                                                ? 'any'
                                                : 'all',
                                        conditions: [],
                                    },
                                ],
                            })
                        }
                    >
                        <Plus /> {t('Group')}
                    </Button>
                )}
            </div>
        </div>
    );
}
