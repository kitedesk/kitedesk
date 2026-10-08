import { Head, Link } from '@inertiajs/react';
import { Mail, Pencil } from 'lucide-react';
import { AdminPageHeader } from '@/components/admin/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useTranslation } from '@/hooks/use-translation';
import { edit, index } from '@/routes/admin/email-templates';
import type { EmailTemplate } from './edit';

export default function EmailTemplatesIndex({
    templates,
}: {
    templates: EmailTemplate[];
}) {
    const { t } = useTranslation();

    return (
        <>
            <Head title={t('Email templates')} />
            <AdminPageHeader
                title={t('Email templates')}
                description={t(
                    'The automatic emails KiteDesk sends. Reword them or switch them off.',
                )}
            />

            <ul className="divide-y overflow-hidden rounded-xl border bg-card shadow-xs">
                {templates.map((template) => (
                    <li
                        key={template.event}
                        className="flex items-center gap-3 px-4 py-3"
                    >
                        <Mail className="size-4 shrink-0 text-muted-foreground" />
                        <div className="min-w-0 flex-1">
                            <p className="flex flex-wrap items-center gap-2 font-medium">
                                <Link
                                    href={edit(template.event)}
                                    className="hover:underline"
                                >
                                    {template.label}
                                </Link>
                                {!template.is_active && (
                                    <Badge variant="outline">{t('Off')}</Badge>
                                )}
                                {template.is_customized && (
                                    <Badge variant="secondary">
                                        {t('Customized')}
                                    </Badge>
                                )}
                            </p>
                            <p className="text-xs text-muted-foreground">
                                {template.description}
                            </p>
                        </div>
                        <Button
                            variant="ghost"
                            size="icon"
                            title={t('Edit')}
                            asChild
                        >
                            <Link href={edit(template.event)}>
                                <Pencil />
                            </Link>
                        </Button>
                    </li>
                ))}
            </ul>
        </>
    );
}

EmailTemplatesIndex.layout = {
    breadcrumbs: [
        { title: 'Admin center', href: '/admin' },
        { title: 'Email templates', href: index() },
    ],
};
