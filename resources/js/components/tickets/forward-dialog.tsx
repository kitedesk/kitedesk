import { useForm } from '@inertiajs/react';
import { Forward, User, Users } from 'lucide-react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { RichTextEditor } from '@/components/tickets/rich-text-editor';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectLabel,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useTranslation } from '@/hooks/use-translation';
import { forward } from '@/routes/agent/tickets';
import type { NamedRecord } from '@/types';

/**
 * Hand the ticket over to another agent or to a group's queue, with a note for them.
 */
export function ForwardDialog({
    ticketId,
    ticketNumber,
    agents,
    groups,
    currentUserId,
    assigneeId,
    reloadProps,
}: {
    ticketId: number;
    ticketNumber: string;
    agents: NamedRecord[];
    groups: NamedRecord[];
    currentUserId: number;
    assigneeId: number | null;
    reloadProps: string[];
}) {
    const { t } = useTranslation();
    const [open, setOpen] = useState(false);
    const form = useForm<{
        assignee_id: number | null;
        group_id: number | null;
        note: string;
    }>({ assignee_id: null, group_id: null, note: '' });

    const otherAgents = agents.filter(
        (agent) => agent.id !== currentUserId && agent.id !== assigneeId,
    );
    const toGroup = form.data.group_id !== null;
    const hasTarget = toGroup || form.data.assignee_id !== null;
    const target = toGroup
        ? `group:${form.data.group_id}`
        : hasTarget
          ? `agent:${form.data.assignee_id}`
          : '';

    const choose = (value: string) => {
        const [kind, id] = value.split(':');

        form.setData((data) => ({
            ...data,
            assignee_id: kind === 'agent' ? Number(id) : null,
            group_id: kind === 'group' ? Number(id) : null,
        }));
    };

    const submit = () => {
        form.post(forward.url(ticketId), {
            preserveScroll: true,
            only: reloadProps,
            onSuccess: () => setOpen(false),
        });
    };

    return (
        <Dialog
            open={open}
            onOpenChange={(next) => {
                setOpen(next);
                form.reset();
                form.clearErrors();
            }}
        >
            <DialogTrigger asChild>
                <Button variant="outline" size="sm">
                    <Forward /> {t('Forward')}
                </Button>
            </DialogTrigger>
            <DialogContent className="sm:max-w-xl">
                <DialogTitle>
                    {t('Forward :number', { number: ticketNumber })}
                </DialogTitle>
                <DialogDescription>
                    {toGroup
                        ? t(
                              'The ticket moves to that group unassigned, and its members are notified unless the group assigns it to someone.',
                          )
                        : t(
                              'The ticket is assigned to this agent and they are notified. Your note is added as an internal note.',
                          )}
                </DialogDescription>

                <div className="space-y-2">
                    <Label htmlFor="forward-target">{t('Forward to')}</Label>
                    <Select value={target} onValueChange={choose}>
                        <SelectTrigger id="forward-target" className="w-full">
                            <SelectValue
                                placeholder={t('Choose an agent or a group')}
                            />
                        </SelectTrigger>
                        <SelectContent>
                            {groups.length > 0 && (
                                <SelectGroup>
                                    <SelectLabel>{t('Groups')}</SelectLabel>
                                    {groups.map((group) => (
                                        <SelectItem
                                            key={`group-${group.id}`}
                                            value={`group:${group.id}`}
                                        >
                                            <Users className="text-muted-foreground" />
                                            {group.name}
                                        </SelectItem>
                                    ))}
                                </SelectGroup>
                            )}
                            {otherAgents.length > 0 && (
                                <SelectGroup>
                                    <SelectLabel>{t('Agents')}</SelectLabel>
                                    {otherAgents.map((agent) => (
                                        <SelectItem
                                            key={`agent-${agent.id}`}
                                            value={`agent:${agent.id}`}
                                        >
                                            <User className="text-muted-foreground" />
                                            {agent.name}
                                        </SelectItem>
                                    ))}
                                </SelectGroup>
                            )}
                        </SelectContent>
                    </Select>
                    <InputError
                        message={
                            form.errors.assignee_id ?? form.errors.group_id
                        }
                    />
                </div>

                <div className="space-y-2">
                    <Label>{t('Note (optional)')}</Label>
                    <RichTextEditor
                        value={form.data.note}
                        onChange={(html) => form.setData('note', html)}
                        placeholder={t(
                            'What should they know? What has been tried so far?',
                        )}
                        tone="internal"
                        minHeight="6rem"
                        onSubmit={() => hasTarget && submit()}
                    />
                    <InputError message={form.errors.note} />
                </div>

                <DialogFooter>
                    <Button
                        disabled={!hasTarget || form.processing}
                        onClick={submit}
                    >
                        <Forward /> {t('Forward ticket')}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
