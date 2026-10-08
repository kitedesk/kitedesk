import { Form, Head } from '@inertiajs/react';
import { Pencil, Plus, Trash2, UsersRound } from 'lucide-react';
import { useState } from 'react';
import { ConfirmAction } from '@/components/admin/confirm-action';
import { MultiSelectChips } from '@/components/admin/multi-select-chips';
import { AdminPageHeader } from '@/components/admin/page-header';
import InputError from '@/components/input-error';
import { AvatarStack } from '@/components/ui/avatar-stack';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useTranslation } from '@/hooks/use-translation';
import { destroy, index, store, update } from '@/routes/admin/groups';
import type { NamedRecord, Option } from '@/types';

type Group = {
    id: number;
    name: string;
    description: string | null;
    assignment_mode: string;
    agents: NamedRecord[];
    open_tickets_count: number;
};

export default function GroupsIndex({
    groups,
    staff,
    assignmentModes,
}: {
    groups: Group[];
    staff: NamedRecord[];
    assignmentModes: Option[];
}) {
    const { t, tChoice } = useTranslation();
    const [editing, setEditing] = useState<Group | 'new' | null>(null);

    return (
        <>
            <Head title={t('Groups')} />
            <AdminPageHeader
                title={t('Groups')}
                description={t(
                    "Groups route tickets to the right team. Agents see 'Your groups' in their views.",
                )}
                actions={
                    <Button onClick={() => setEditing('new')}>
                        <Plus /> {t('New group')}
                    </Button>
                }
            />

            {groups.length === 0 ? (
                <div className="rounded-xl border border-dashed px-6 py-16 text-center text-sm text-muted-foreground">
                    <UsersRound className="mx-auto mb-2 size-6" />
                    {t('Create your first group, e.g. “Support” or “Billing”.')}
                </div>
            ) : (
                <div className="grid gap-3 lg:grid-cols-2">
                    {groups.map((group) => (
                        <div
                            key={group.id}
                            className="flex flex-col gap-3 rounded-xl border bg-card p-4 shadow-xs"
                        >
                            <div className="flex items-start justify-between gap-2">
                                <div>
                                    <p className="font-medium">{group.name}</p>
                                    {group.description && (
                                        <p className="text-sm text-muted-foreground">
                                            {group.description}
                                        </p>
                                    )}
                                    {group.assignment_mode !== 'manual' && (
                                        <p className="text-xs text-primary">
                                            {t('Auto-assign: :mode', {
                                                mode:
                                                    assignmentModes.find(
                                                        (mode) =>
                                                            mode.value ===
                                                            group.assignment_mode,
                                                    )?.label ??
                                                    group.assignment_mode,
                                            })}
                                        </p>
                                    )}
                                </div>
                                <div className="flex gap-1">
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        title={t('Edit')}
                                        onClick={() => setEditing(group)}
                                    >
                                        <Pencil />
                                    </Button>
                                    <ConfirmAction
                                        trigger={
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                title={t('Delete')}
                                            >
                                                <Trash2 />
                                            </Button>
                                        }
                                        title={t('Delete :name?', {
                                            name: group.name,
                                        })}
                                        description={t(
                                            'Tickets in this group will become ungrouped.',
                                        )}
                                        href={destroy.url(group.id)}
                                    />
                                </div>
                            </div>
                            <div className="mt-auto flex items-center justify-between text-xs text-muted-foreground">
                                {group.agents.length ? (
                                    <AvatarStack
                                        size="sm"
                                        max={6}
                                        showTooltip
                                        avatars={group.agents.map((agent) => ({
                                            id: agent.id,
                                            name: agent.name,
                                            alt: agent.name,
                                        }))}
                                    />
                                ) : (
                                    <span>{t('No agents')}</span>
                                )}
                                <span>
                                    {tChoice(
                                        ':count open ticket|:count open tickets',
                                        group.open_tickets_count,
                                    )}
                                </span>
                            </div>
                        </div>
                    ))}
                </div>
            )}

            <Dialog
                open={editing !== null}
                onOpenChange={(open) => !open && setEditing(null)}
            >
                <DialogContent>
                    {editing !== null && (
                        <GroupForm
                            group={editing === 'new' ? null : editing}
                            staff={staff}
                            assignmentModes={assignmentModes}
                            onDone={() => setEditing(null)}
                        />
                    )}
                </DialogContent>
            </Dialog>
        </>
    );
}

function GroupForm({
    group,
    staff,
    assignmentModes,
    onDone,
}: {
    group: Group | null;
    staff: NamedRecord[];
    assignmentModes: Option[];
    onDone: () => void;
}) {
    const { t } = useTranslation();
    const [agentIds, setAgentIds] = useState<number[]>(
        group?.agents.map((agent) => agent.id) ?? [],
    );
    const [assignmentMode, setAssignmentMode] = useState(
        group?.assignment_mode ?? 'manual',
    );

    return (
        <Form
            {...(group ? update.form(group.id) : store.form())}
            transform={(data) => ({
                ...data,
                agent_ids: agentIds,
                assignment_mode: assignmentMode,
            })}
            onSuccess={onDone}
            options={{ preserveScroll: true }}
            className="space-y-4"
        >
            {({ errors, processing }) => (
                <>
                    <DialogTitle>
                        {group ? t('Edit group') : t('New group')}
                    </DialogTitle>
                    <DialogDescription>
                        {t('Pick the agents who work tickets for this group.')}
                    </DialogDescription>
                    <div className="grid gap-2">
                        <Label htmlFor="group-name">{t('Name')}</Label>
                        <Input
                            id="group-name"
                            name="name"
                            defaultValue={group?.name}
                            required
                            autoFocus
                        />
                        <InputError message={errors.name} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="group-description">
                            {t('Description')}
                        </Label>
                        <Input
                            id="group-description"
                            name="description"
                            defaultValue={group?.description ?? ''}
                        />
                    </div>
                    <div className="grid gap-2">
                        <Label>{t('Agents')}</Label>
                        <MultiSelectChips
                            options={staff.map((member) => ({
                                value: member.id,
                                label: member.name,
                            }))}
                            value={agentIds}
                            onChange={setAgentIds}
                        />
                    </div>
                    <div className="grid gap-2">
                        <Label>{t('Assign new tickets')}</Label>
                        <Select
                            value={assignmentMode}
                            onValueChange={setAssignmentMode}
                        >
                            <SelectTrigger className="w-full">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {assignmentModes.map((mode) => (
                                    <SelectItem
                                        key={mode.value}
                                        value={mode.value}
                                    >
                                        {mode.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <p className="text-xs text-muted-foreground">
                            {t(
                                'Unassigned tickets that land in this group go to an available agent. Light agents and agents who are away are skipped.',
                            )}
                        </p>
                        <InputError message={errors.assignment_mode} />
                    </div>
                    <div className="flex justify-end">
                        <Button type="submit" disabled={processing}>
                            {t('Save')}
                        </Button>
                    </div>
                </>
            )}
        </Form>
    );
}

GroupsIndex.layout = {
    breadcrumbs: [
        { title: 'Admin center', href: '/admin' },
        { title: 'Groups', href: index() },
    ],
};
