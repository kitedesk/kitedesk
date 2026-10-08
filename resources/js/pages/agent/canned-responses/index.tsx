import { Head, useForm } from '@inertiajs/react';
import { MessageSquareText, Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { ConfirmAction } from '@/components/admin/confirm-action';
import { AdminPageHeader } from '@/components/admin/page-header';
import { TextField } from '@/components/admin/text-field';
import InputError from '@/components/input-error';
import { RichTextEditor } from '@/components/tickets/rich-text-editor';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useTranslation } from '@/hooks/use-translation';
import { destroy, index, store, update } from '@/routes/agent/canned-responses';
import type { NamedRecord } from '@/types';

type CannedResponse = {
    id: number;
    title: string;
    body: string;
    is_shared: boolean;
    group: NamedRecord | null;
    owner: string | null;
    can_edit: boolean;
};

const ONLY_ME = 'me';
const EVERYONE = 'everyone';

const placeholders = [
    'requester.name',
    'requester.first_name',
    'requester.email',
    'ticket.number',
    'ticket.id',
    'ticket.subject',
    'agent.name',
    'agent.first_name',
];

export default function CannedResponsesIndex({
    responses,
    groups,
}: {
    responses: CannedResponse[];
    /** Groups an admin can share with; empty for agents. */
    groups: NamedRecord[];
}) {
    const { t } = useTranslation();
    const [editing, setEditing] = useState<CannedResponse | 'new' | null>(null);

    const audience = (response: CannedResponse) =>
        response.is_shared
            ? t('Everyone')
            : response.group
              ? response.group.name
              : t('Only :name', { name: response.owner ?? '' });

    return (
        <div className="mx-auto w-full max-w-4xl p-4 md:p-8">
            <Head title={t('Canned responses')} />
            <AdminPageHeader
                title={t('Canned responses')}
                description={t(
                    'Reusable replies you can insert from the ticket composer.',
                )}
                actions={
                    <Button onClick={() => setEditing('new')}>
                        <Plus /> {t('New response')}
                    </Button>
                }
            />

            {responses.length === 0 ? (
                <div className="rounded-xl border border-dashed px-6 py-16 text-center text-sm text-muted-foreground">
                    <MessageSquareText className="mx-auto mb-2 size-6" />
                    {t('No canned responses yet.')}
                </div>
            ) : (
                <ul className="divide-y overflow-hidden rounded-xl border bg-card shadow-xs">
                    {responses.map((response) => (
                        <li
                            key={response.id}
                            className="flex items-start gap-3 px-4 py-3"
                        >
                            <div className="min-w-0 flex-1">
                                <p className="flex flex-wrap items-center gap-2 font-medium">
                                    {response.title}
                                    <Badge variant="secondary">
                                        {audience(response)}
                                    </Badge>
                                </p>
                                <div
                                    className="prose-ticket line-clamp-2 text-sm text-muted-foreground"
                                    dangerouslySetInnerHTML={{
                                        __html: response.body,
                                    }}
                                />
                            </div>
                            {response.can_edit && (
                                <>
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        title={t('Edit')}
                                        onClick={() => setEditing(response)}
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
                                        title={t('Delete “:name”?', {
                                            name: response.title,
                                        })}
                                        description={t(
                                            'Replies already sent are not affected.',
                                        )}
                                        href={destroy.url(response.id)}
                                    />
                                </>
                            )}
                        </li>
                    ))}
                </ul>
            )}

            <Dialog
                open={editing !== null}
                onOpenChange={(open) => !open && setEditing(null)}
            >
                <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
                    {editing !== null && (
                        <ResponseForm
                            response={editing === 'new' ? null : editing}
                            groups={groups}
                            onDone={() => setEditing(null)}
                        />
                    )}
                </DialogContent>
            </Dialog>
        </div>
    );
}

function ResponseForm({
    response,
    groups,
    onDone,
}: {
    response: CannedResponse | null;
    groups: NamedRecord[];
    onDone: () => void;
}) {
    const { t } = useTranslation();
    const canShare = groups.length > 0;
    const [audience, setAudience] = useState(
        response?.is_shared
            ? EVERYONE
            : response?.group
              ? String(response.group.id)
              : ONLY_ME,
    );
    const form = useForm({
        title: response?.title ?? '',
        body: response?.body ?? '',
    });

    const submit = (event: React.FormEvent) => {
        event.preventDefault();

        form.transform((data) => ({
            ...data,
            ...(canShare
                ? {
                      is_shared: audience === EVERYONE,
                      group_id:
                          audience === EVERYONE || audience === ONLY_ME
                              ? null
                              : Number(audience),
                  }
                : {}),
        }));

        const options = { preserveScroll: true, onSuccess: onDone };

        if (response) {
            form.put(update.url(response.id), options);
        } else {
            form.post(store.url(), options);
        }
    };

    return (
        <form onSubmit={submit} className="space-y-4">
            <DialogTitle>
                {response ? t('Edit response') : t('New response')}
            </DialogTitle>
            <DialogDescription>
                {t('Placeholders are replaced when you insert the response:')}{' '}
                {placeholders.map((placeholder) => (
                    <code
                        key={placeholder}
                        className="mr-1 rounded bg-muted px-1 text-xs"
                    >
                        {`{{${placeholder}}}`}
                    </code>
                ))}
            </DialogDescription>

            <TextField
                id="response-title"
                label={t('Title')}
                value={form.data.title}
                onChange={(title) => form.setData('title', title)}
                error={form.errors.title}
                required
            />

            <div className="grid gap-2">
                <Label>{t('Response')}</Label>
                <div className="rounded-md border">
                    <RichTextEditor
                        value={form.data.body}
                        onChange={(body) => form.setData('body', body)}
                        placeholder={t('Hi {{requester.first_name}}, …')}
                    />
                </div>
                <InputError message={form.errors.body} />
            </div>

            {canShare && (
                <div className="grid gap-2">
                    <Label>{t('Available to')}</Label>
                    <Select value={audience} onValueChange={setAudience}>
                        <SelectTrigger className="w-full">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value={ONLY_ME}>
                                {t('Only me')}
                            </SelectItem>
                            <SelectItem value={EVERYONE}>
                                {t('Everyone')}
                            </SelectItem>
                            {groups.map((group) => (
                                <SelectItem
                                    key={group.id}
                                    value={String(group.id)}
                                >
                                    {t('Group: :name', { name: group.name })}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>
            )}

            <div className="flex justify-end">
                <Button type="submit" disabled={form.processing}>
                    {t('Save')}
                </Button>
            </div>
        </form>
    );
}

CannedResponsesIndex.layout = {
    breadcrumbs: [{ title: 'Canned responses', href: index() }],
};
