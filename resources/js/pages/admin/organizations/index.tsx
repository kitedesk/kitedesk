import { Form, Head, router } from '@inertiajs/react';
import { Building2, Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { ConfirmAction } from '@/components/admin/confirm-action';
import { AdminPageHeader } from '@/components/admin/page-header';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { SlidePagination } from '@/components/ui/slide-pagination';
import { useTranslation } from '@/hooks/use-translation';
import { destroy, index, store, update } from '@/routes/admin/organizations';

type Organization = {
    id: number;
    name: string;
    domains: string[] | null;
    notes: string | null;
    members_count: number;
    tickets_count: number;
};

type Props = {
    search: string;
    organizations: {
        data: Organization[];
        current_page: number;
        last_page: number;
    };
};

export default function OrganizationsIndex({ search, organizations }: Props) {
    const { t } = useTranslation();
    const [editing, setEditing] = useState<Organization | 'new' | null>(null);

    return (
        <>
            <Head title={t('Organizations')} />
            <AdminPageHeader
                title={t('Organizations')}
                description={t(
                    "Customers whose email matches an organization's domain are linked to it automatically when they open a ticket.",
                )}
                actions={
                    <Button onClick={() => setEditing('new')}>
                        <Plus /> {t('New organization')}
                    </Button>
                }
            />

            <Input
                defaultValue={search}
                placeholder={t('Search organizations…')}
                className="mb-4 max-w-xs"
                onChange={(event) =>
                    router.get(
                        index.url(),
                        { search: event.target.value },
                        { preserveState: true, replace: true },
                    )
                }
            />

            <div className="overflow-hidden rounded-xl border bg-card shadow-xs">
                <table className="w-full text-sm">
                    <thead>
                        <tr className="border-b bg-muted/40 text-left text-xs text-muted-foreground">
                            <th className="px-4 py-2.5 font-medium">
                                {t('Name')}
                            </th>
                            <th className="px-3 py-2.5 font-medium">
                                {t('Domains')}
                            </th>
                            <th className="hidden px-3 py-2.5 font-medium sm:table-cell">
                                {t('People')}
                            </th>
                            <th className="hidden px-3 py-2.5 font-medium sm:table-cell">
                                {t('Tickets')}
                            </th>
                            <th className="w-24 px-4 py-2.5" />
                        </tr>
                    </thead>
                    <tbody>
                        {organizations.data.map((organization) => (
                            <tr
                                key={organization.id}
                                className="border-b last:border-0"
                            >
                                <td className="px-4 py-3 font-medium">
                                    <span className="flex items-center gap-2">
                                        <Building2 className="size-4 text-muted-foreground" />
                                        {organization.name}
                                    </span>
                                </td>
                                <td className="px-3 py-3 text-muted-foreground">
                                    {organization.domains?.join(', ') || '—'}
                                </td>
                                <td className="hidden px-3 py-3 sm:table-cell">
                                    {organization.members_count}
                                </td>
                                <td className="hidden px-3 py-3 sm:table-cell">
                                    {organization.tickets_count}
                                </td>
                                <td className="px-4 py-3">
                                    <div className="flex justify-end gap-1">
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            title={t('Edit')}
                                            onClick={() =>
                                                setEditing(organization)
                                            }
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
                                                name: organization.name,
                                            })}
                                            description={t(
                                                'People and tickets are kept but unlinked from this organization.',
                                            )}
                                            href={destroy.url(organization.id)}
                                        />
                                    </div>
                                </td>
                            </tr>
                        ))}
                        {organizations.data.length === 0 && (
                            <tr>
                                <td
                                    colSpan={5}
                                    className="px-4 py-12 text-center text-muted-foreground"
                                >
                                    {t('No organizations yet.')}
                                </td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>

            {organizations.last_page > 1 && (
                <div className="mt-4 flex justify-center">
                    <SlidePagination
                        pageCount={organizations.last_page}
                        page={organizations.current_page}
                        onChange={(page) =>
                            router.get(
                                index.url(),
                                { search, page },
                                { preserveState: true },
                            )
                        }
                    />
                </div>
            )}

            <Dialog
                open={editing !== null}
                onOpenChange={(open) => !open && setEditing(null)}
            >
                <DialogContent>
                    {editing !== null && (
                        <Form
                            {...(editing === 'new'
                                ? store.form()
                                : update.form(editing.id))}
                            onSuccess={() => setEditing(null)}
                            options={{ preserveScroll: true }}
                            className="space-y-4"
                        >
                            {({ errors, processing }) => (
                                <>
                                    <DialogTitle>
                                        {editing === 'new'
                                            ? t('New organization')
                                            : t('Edit organization')}
                                    </DialogTitle>
                                    <DialogDescription>
                                        {t(
                                            'Separate multiple domains with commas.',
                                        )}
                                    </DialogDescription>
                                    <div className="grid gap-2">
                                        <Label htmlFor="org-name">
                                            {t('Name')}
                                        </Label>
                                        <Input
                                            id="org-name"
                                            name="name"
                                            defaultValue={
                                                editing === 'new'
                                                    ? ''
                                                    : editing.name
                                            }
                                            required
                                            autoFocus
                                        />
                                        <InputError message={errors.name} />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="org-domains">
                                            {t('Email domains')}
                                        </Label>
                                        <Input
                                            id="org-domains"
                                            name="domains"
                                            placeholder="acme.com, acme.io"
                                            defaultValue={
                                                editing === 'new'
                                                    ? ''
                                                    : (
                                                          editing.domains ?? []
                                                      ).join(', ')
                                            }
                                        />
                                        <InputError
                                            message={
                                                errors.domains ??
                                                Object.entries(errors).find(
                                                    ([key]) =>
                                                        key.startsWith(
                                                            'domains.',
                                                        ),
                                                )?.[1]
                                            }
                                        />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="org-notes">
                                            {t('Notes')}
                                        </Label>
                                        <textarea
                                            id="org-notes"
                                            name="notes"
                                            rows={3}
                                            defaultValue={
                                                editing === 'new'
                                                    ? ''
                                                    : (editing.notes ?? '')
                                            }
                                            className="rounded-md border bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30"
                                        />
                                    </div>
                                    <div className="flex justify-end">
                                        <Button
                                            type="submit"
                                            disabled={processing}
                                        >
                                            {t('Save')}
                                        </Button>
                                    </div>
                                </>
                            )}
                        </Form>
                    )}
                </DialogContent>
            </Dialog>
        </>
    );
}

OrganizationsIndex.layout = {
    breadcrumbs: [
        { title: 'Admin center', href: '/admin' },
        { title: 'Organizations', href: index() },
    ],
};
