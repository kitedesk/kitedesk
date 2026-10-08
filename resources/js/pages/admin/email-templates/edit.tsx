import { Head, Link, setLayoutProps, useForm, usePage } from '@inertiajs/react';
import { RotateCcw } from 'lucide-react';
import { ConfirmAction } from '@/components/admin/confirm-action';
import { AdminPageHeader } from '@/components/admin/page-header';
import { TextField } from '@/components/admin/text-field';
import { ActiveSwitch } from '@/components/sla/active-switch';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { useEntitlements } from '@/hooks/use-entitlements';
import { useTranslation } from '@/hooks/use-translation';
import { destroy, edit, index, update } from '@/routes/admin/email-templates';

export type EmailTemplate = {
    event: string;
    label: string;
    description: string;
    subject: string;
    body: string;
    is_active: boolean;
    is_customized: boolean;
    placeholders: string[];
    default_subject: string;
    default_body: string;
};

/**
 * Sample values for the live preview.
 */
function sampleValues(appName: string): Record<string, string> {
    return {
        'ticket.number': '#1042',
        'ticket.id': '1042',
        'ticket.subject': 'Cannot export my invoices',
        'ticket.status': 'Open',
        'ticket.url': 'https://support.example.com/portal/tickets/1042',
        'requester.name': 'Casey Customer',
        'requester.first_name': 'Casey',
        'requester.email': 'casey@example.com',
        'recipient.name': 'Casey Customer',
        'author.name': 'Sam Agent',
        'app.name': appName,
    };
}

function fill(template: string, values: Record<string, string>): string {
    return template.replace(
        /\{\{\s*([a-z_.]+)\s*\}\}/gi,
        (match, key: string) => values[key] ?? match,
    );
}

export default function EditEmailTemplate({
    template,
}: {
    template: EmailTemplate;
}) {
    const { t } = useTranslation();
    const { name } = usePage().props;
    const customWording = useEntitlements().includes('custom_email_templates');
    const form = useForm({
        subject: template.subject,
        body: template.body,
        is_active: template.is_active,
    });
    const values = sampleValues(name);

    setLayoutProps({
        breadcrumbs: [
            { title: 'Admin center', href: '/admin' },
            { title: 'Email templates', href: index() },
            { title: template.label, href: edit(template.event) },
        ],
    });

    return (
        <>
            <Head title={template.label} />
            <AdminPageHeader
                title={template.label}
                description={template.description}
                actions={
                    template.is_customized ? (
                        <ConfirmAction
                            trigger={
                                <Button variant="outline">
                                    <RotateCcw /> {t('Reset to default')}
                                </Button>
                            }
                            title={t('Reset this email?')}
                            description={t(
                                'Your wording is replaced by the default text in the installation language.',
                            )}
                            confirmLabel={t('Reset')}
                            href={destroy.url(template.event)}
                        />
                    ) : undefined
                }
            />

            <div className="grid gap-8 lg:grid-cols-2">
                <form
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.put(update.url(template.event));
                    }}
                    className="space-y-4"
                >
                    <label className="flex items-center gap-3 text-sm">
                        <ActiveSwitch
                            checked={form.data.is_active}
                            onChange={(checked) =>
                                form.setData('is_active', checked)
                            }
                            label={t('Send this email')}
                        />
                        {t('Send this email')}
                    </label>
                    <TextField
                        id="template-subject"
                        label={t('Subject')}
                        value={form.data.subject}
                        onChange={(subject) => form.setData('subject', subject)}
                        error={form.errors.subject}
                        required
                        disabled={!customWording}
                    />
                    <div className="grid gap-2">
                        <Label htmlFor="template-body">{t('Message')}</Label>
                        <textarea
                            id="template-body"
                            value={form.data.body}
                            rows={10}
                            required
                            disabled={!customWording}
                            onChange={(event) =>
                                form.setData('body', event.target.value)
                            }
                            className="w-full rounded-md border bg-transparent px-3 py-2 font-mono text-sm shadow-xs outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-50 dark:bg-input/30"
                        />
                        {form.errors.body && (
                            <p className="text-sm text-destructive">
                                {form.errors.body}
                            </p>
                        )}
                    </div>
                    {!customWording && (
                        <p className="text-xs text-muted-foreground">
                            {t(
                                'Your plan uses the default wording. You can still choose whether this email is sent.',
                            )}
                        </p>
                    )}
                    <div className={customWording ? 'space-y-1.5' : 'hidden'}>
                        <p className="text-xs font-medium text-muted-foreground">
                            {t('Placeholders (click to insert)')}
                        </p>
                        <div className="flex flex-wrap gap-1">
                            {template.placeholders.map((placeholder) => (
                                <button
                                    key={placeholder}
                                    type="button"
                                    onClick={() =>
                                        form.setData(
                                            'body',
                                            `${form.data.body}{{${placeholder}}}`,
                                        )
                                    }
                                    className="rounded bg-muted px-1.5 py-0.5 font-mono text-xs hover:bg-accent"
                                >
                                    {`{{${placeholder}}}`}
                                </button>
                            ))}
                        </div>
                    </div>
                    <div className="flex justify-end gap-2">
                        <Button variant="ghost" asChild>
                            <Link href={index()}>{t('Cancel')}</Link>
                        </Button>
                        <Button type="submit" disabled={form.processing}>
                            {t('Save')}
                        </Button>
                    </div>
                </form>

                <section className="space-y-2">
                    <p className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                        {t('Preview')}
                    </p>
                    <div className="overflow-hidden rounded-xl border bg-card shadow-xs">
                        <div className="border-b bg-muted/40 px-4 py-2 text-sm">
                            <span className="text-muted-foreground">
                                {t('Subject')}:
                            </span>{' '}
                            <span className="font-medium">
                                {fill(form.data.subject, values)}
                            </span>
                        </div>
                        <div className="px-4 py-4 text-sm whitespace-pre-line">
                            {fill(form.data.body, values)}
                        </div>
                        {template.event === 'agent_reply' && (
                            <div className="mx-4 mb-4 rounded-md border-l-2 border-primary bg-muted/50 px-3 py-2 text-sm text-muted-foreground">
                                {t('The reply is shown here.')}
                            </div>
                        )}
                    </div>
                </section>
            </div>
        </>
    );
}
