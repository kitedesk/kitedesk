import { router } from '@inertiajs/react';
import { X } from 'lucide-react';
import { useState } from 'react';
import type { ReactNode } from 'react';
import { CategorySelect } from '@/components/tickets/category-select';
import { CollaboratorEditor } from '@/components/tickets/collaborator-editor';
import { CustomFieldInput } from '@/components/tickets/custom-field-input';
import { CustomStatusSelect } from '@/components/tickets/custom-status-select';
import { StarRating } from '@/components/tickets/star-rating';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useTranslation } from '@/hooks/use-translation';
import { cn } from '@/lib/utils';
import { update } from '@/routes/agent/tickets';
import type {
    Ticket,
    TicketField,
    TicketOptions,
    TicketSatisfaction,
} from '@/types';

const NONE = '__none';

type Props = {
    ticket: Ticket;
    options: TicketOptions;
    fields: TicketField[];
    currentUserId: number;
    satisfaction?: TicketSatisfaction | null;
    disabled?: boolean;
};

/**
 * Left-hand ticket properties. Every change is saved immediately (like Zendesk's
 * property panel), and the audit trail records who changed what.
 */
export function TicketProperties({
    ticket,
    options,
    fields,
    currentUserId,
    satisfaction = null,
    disabled = false,
}: Props) {
    const { t } = useTranslation();
    const save = (changes: Record<string, unknown>) =>
        router.patch(update.url(ticket.id), changes as never, {
            preserveScroll: true,
            preserveState: true,
            only: ['ticket', 'ticketFields', 'activity', 'agentNav'],
        });

    return (
        <div className="space-y-4">
            <Property label={t('Assignee')}>
                <PropertySelect
                    value={ticket.assignee ? String(ticket.assignee.id) : NONE}
                    disabled={disabled}
                    noneLabel={t('Unassigned')}
                    options={options.agents.map((agent) => ({
                        value: String(agent.id),
                        label: agent.name,
                    }))}
                    onChange={(value) =>
                        save({
                            assignee_id: value === NONE ? null : Number(value),
                        })
                    }
                />
                {!disabled && ticket.assignee?.id !== currentUserId && (
                    <button
                        type="button"
                        onClick={() => save({ assignee_id: currentUserId })}
                        className="mt-1 text-xs font-medium text-primary hover:underline"
                    >
                        {t('Take it')}
                    </button>
                )}
            </Property>

            <Property label={t('Group')}>
                <PropertySelect
                    value={ticket.group ? String(ticket.group.id) : NONE}
                    disabled={disabled}
                    noneLabel={t('No group')}
                    options={options.groups.map((group) => ({
                        value: String(group.id),
                        label: group.name,
                    }))}
                    onChange={(value) =>
                        save({
                            group_id: value === NONE ? null : Number(value),
                        })
                    }
                />
            </Property>

            {options.categories.length > 0 && (
                <Property label={t('Category')}>
                    <CategorySelect
                        categories={options.categories}
                        value={ticket.category?.id ?? null}
                        disabled={disabled}
                        allowNone
                        layout="inline"
                        onChange={(id) => {
                            // Picking a category with subcategories waits for the subcategory.
                            const node = options.categories.find(
                                (category) => category.id === id,
                            );

                            if (!node?.children?.length) {
                                save({ category_id: id });
                            }
                        }}
                    />
                </Property>
            )}

            <div className="grid grid-cols-2 gap-3">
                <Property label={t('Status')}>
                    <CustomStatusSelect
                        value={
                            ticket.custom_status
                                ? String(ticket.custom_status.id)
                                : undefined
                        }
                        statuses={options.customStatuses}
                        disabled={disabled}
                        onChange={(value) =>
                            save({ ticket_status_id: Number(value) })
                        }
                    />
                </Property>

                <Property label={t('Priority')}>
                    <PropertySelect
                        value={ticket.priority}
                        disabled={disabled}
                        options={options.priorities}
                        onChange={(value) => save({ priority: value })}
                    />
                </Property>
            </div>

            <Property label={t('Type')}>
                <PropertySelect
                    value={ticket.type ?? NONE}
                    disabled={disabled}
                    noneLabel="—"
                    options={options.types}
                    onChange={(value) =>
                        save({ type: value === NONE ? null : value })
                    }
                />
            </Property>

            <Property label={t('CC')}>
                <CollaboratorEditor
                    ticketId={ticket.id}
                    collaborators={ticket.collaborators ?? []}
                    disabled={disabled}
                />
            </Property>

            <Property label={t('Tags')}>
                <TagEditor
                    tags={ticket.tags ?? []}
                    suggestions={options.tags}
                    disabled={disabled}
                    onChange={(tags) => save({ tags })}
                />
            </Property>

            {fields.map((field) => (
                <Property key={field.id} label={field.label}>
                    <CustomFieldInput
                        key={`${field.id}-${ticket.updated_at}`}
                        field={field}
                        value={ticket.custom_fields[field.key]}
                        disabled={disabled}
                        commitOnBlur
                        onChange={(value) =>
                            save({ custom_fields: { [field.key]: value } })
                        }
                    />
                </Property>
            ))}

            {satisfaction && (
                <Property label={t('Satisfaction')}>
                    {satisfaction.score ? (
                        <div className="space-y-1">
                            <StarRating value={satisfaction.score} />
                            {satisfaction.comment && (
                                <p className="text-sm whitespace-pre-line text-muted-foreground">
                                    “{satisfaction.comment}”
                                </p>
                            )}
                        </div>
                    ) : (
                        <p className="text-sm text-muted-foreground">
                            {t('Survey sent, no answer yet')}
                        </p>
                    )}
                </Property>
            )}
        </div>
    );
}

function Property({ label, children }: { label: string; children: ReactNode }) {
    return (
        <div className="grid gap-1.5">
            <Label className="text-xs font-medium text-muted-foreground">
                {label}
            </Label>
            {children}
        </div>
    );
}

function PropertySelect({
    value,
    options,
    onChange,
    disabled,
    noneLabel,
}: {
    value: string;
    options: { value: string; label: string }[];
    onChange: (value: string) => void;
    disabled?: boolean;
    noneLabel?: string;
}) {
    return (
        <Select value={value} disabled={disabled} onValueChange={onChange}>
            <SelectTrigger className="h-9 w-full">
                <SelectValue />
            </SelectTrigger>
            <SelectContent>
                {noneLabel && <SelectItem value={NONE}>{noneLabel}</SelectItem>}
                {options.map((option) => (
                    <SelectItem key={option.value} value={option.value}>
                        {option.label}
                    </SelectItem>
                ))}
            </SelectContent>
        </Select>
    );
}

function TagEditor({
    tags,
    suggestions,
    onChange,
    disabled,
}: {
    tags: string[];
    suggestions: string[];
    onChange: (tags: string[]) => void;
    disabled?: boolean;
}) {
    const { t } = useTranslation();
    const [draft, setDraft] = useState('');

    const add = (raw: string) => {
        const tag = raw.trim().toLowerCase().replace(/\s+/g, '_');

        if (tag && !tags.includes(tag)) {
            onChange([...tags, tag]);
        }

        setDraft('');
    };

    return (
        <div
            className={cn(
                'flex min-h-9 flex-wrap items-center gap-1 rounded-md border bg-transparent px-1.5 py-1 shadow-xs dark:bg-input/30',
                disabled && 'opacity-60',
            )}
        >
            {tags.map((tag) => (
                <span
                    key={tag}
                    className="inline-flex items-center gap-0.5 rounded bg-muted py-0.5 pr-0.5 pl-1.5 text-xs"
                >
                    {tag}
                    {!disabled && (
                        <button
                            type="button"
                            aria-label={t('Remove :name', { name: tag })}
                            onClick={() =>
                                onChange(tags.filter((value) => value !== tag))
                            }
                            className="rounded p-0.5 text-muted-foreground hover:bg-background hover:text-foreground"
                        >
                            <X className="size-3" />
                        </button>
                    )}
                </span>
            ))}
            {!disabled && (
                <>
                    <input
                        value={draft}
                        list="ticket-tag-suggestions"
                        onChange={(event) => setDraft(event.target.value)}
                        onKeyDown={(event) => {
                            if (event.key === 'Enter' || event.key === ',') {
                                event.preventDefault();
                                add(draft);
                            }

                            if (
                                event.key === 'Backspace' &&
                                draft === '' &&
                                tags.length
                            ) {
                                onChange(tags.slice(0, -1));
                            }
                        }}
                        onBlur={() => draft && add(draft)}
                        placeholder={tags.length ? '' : t('Add tags…')}
                        className="min-w-16 flex-1 bg-transparent px-1 text-sm outline-none placeholder:text-muted-foreground"
                    />
                    <datalist id="ticket-tag-suggestions">
                        {suggestions
                            .filter((tag) => !tags.includes(tag))
                            .map((tag) => (
                                <option key={tag} value={tag} />
                            ))}
                    </datalist>
                </>
            )}
        </div>
    );
}
