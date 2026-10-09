import { Head, useForm } from '@inertiajs/react';
import { Link2, X } from 'lucide-react';
import { useState } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { RichTextEditor } from '@/components/tickets/rich-text-editor';
import { TicketSearch } from '@/components/tickets/ticket-search';
import type { TicketMatch } from '@/components/tickets/ticket-search';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { useTranslation } from '@/hooks/use-translation';
import { create, index, store } from '@/routes/agent/requests';
import type { NamedRecord } from '@/types';

type Props = {
    groups: NamedRecord[];
    priorities: { value: string; label: string }[];
};

export default function CreateInternalRequest({ groups, priorities }: Props) {
    const { t } = useTranslation();
    const [related, setRelated] = useState<TicketMatch | null>(null);
    const form = useForm({
        group_id: null as number | null,
        subject: '',
        body: '',
        priority: 'normal',
        related_ticket_id: null as number | null,
    });

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        form.post(store.url());
    };

    return (
        <>
            <Head title={t('New internal request')} />

            <form
                onSubmit={submit}
                className="mx-auto w-full max-w-3xl space-y-6 p-4 md:p-8"
            >
                <Heading
                    title={t('New internal request')}
                    description={t(
                        'Ask another department for something. Their team answers here, and you follow it under My requests.',
                    )}
                />

                <div className="grid gap-4 sm:grid-cols-2">
                    <div className="grid gap-2">
                        <Label htmlFor="group_id">{t('Department')}</Label>
                        <Select
                            value={
                                form.data.group_id === null
                                    ? undefined
                                    : String(form.data.group_id)
                            }
                            onValueChange={(value) =>
                                form.setData('group_id', Number(value))
                            }
                        >
                            <SelectTrigger id="group_id" className="w-full">
                                <SelectValue
                                    placeholder={t('Choose a department')}
                                />
                            </SelectTrigger>
                            <SelectContent>
                                {groups.map((group) => (
                                    <SelectItem
                                        key={group.id}
                                        value={String(group.id)}
                                    >
                                        {group.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={form.errors.group_id} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="priority">{t('Priority')}</Label>
                        <Select
                            value={form.data.priority}
                            onValueChange={(value) =>
                                form.setData('priority', value)
                            }
                        >
                            <SelectTrigger id="priority" className="w-full">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {priorities.map((priority) => (
                                    <SelectItem
                                        key={priority.value}
                                        value={priority.value}
                                    >
                                        {priority.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={form.errors.priority} />
                    </div>
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="subject">{t('Subject')}</Label>
                    <Input
                        id="subject"
                        value={form.data.subject}
                        onChange={(event) =>
                            form.setData('subject', event.target.value)
                        }
                        placeholder={t('Short summary of the request')}
                    />
                    <InputError message={form.errors.subject} />
                </div>

                <div className="grid gap-2">
                    <Label>{t('Description')}</Label>
                    <RichTextEditor
                        value={form.data.body}
                        onChange={(html) => form.setData('body', html)}
                        placeholder={t('Describe the request…')}
                        minHeight="12rem"
                    />
                    <InputError message={form.errors.body} />
                </div>

                <div className="grid gap-2">
                    <Label>{t('Related ticket')}</Label>
                    {related ? (
                        <div className="flex items-center gap-3 rounded-lg border bg-card p-3">
                            <Link2 className="size-4 text-muted-foreground" />
                            <p className="min-w-0 flex-1 truncate text-sm">
                                <span className="text-muted-foreground">
                                    {related.number}
                                </span>{' '}
                                {related.subject}
                            </p>
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                aria-label={t('Remove the related ticket')}
                                onClick={() => {
                                    setRelated(null);
                                    form.setData('related_ticket_id', null);
                                }}
                            >
                                <X />
                            </Button>
                        </div>
                    ) : (
                        <TicketSearch
                            excludeIds={[]}
                            onSelect={(ticket) => {
                                setRelated(ticket);
                                form.setData('related_ticket_id', ticket.id);
                            }}
                        />
                    )}
                    <p className="text-xs text-muted-foreground">
                        {t(
                            'Optional. Links the customer ticket this request is about.',
                        )}
                    </p>
                    <InputError message={form.errors.related_ticket_id} />
                </div>

                <div className="flex justify-end gap-2">
                    <Button
                        type="button"
                        variant="ghost"
                        onClick={() => window.history.back()}
                    >
                        {t('Cancel')}
                    </Button>
                    <Button type="submit" disabled={form.processing}>
                        {form.processing && <Spinner />}
                        {t('Send request')}
                    </Button>
                </div>
            </form>
        </>
    );
}

CreateInternalRequest.layout = {
    breadcrumbs: [
        { title: 'My requests', href: index() },
        { title: 'New internal request', href: create() },
    ],
};
