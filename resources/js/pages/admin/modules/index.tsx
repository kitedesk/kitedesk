import { Head, router, usePage } from '@inertiajs/react';
import { FolderKanban, Puzzle, Receipt, Timer } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useState } from 'react';
import { AdminPageHeader } from '@/components/admin/page-header';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useTranslation } from '@/hooks/use-translation';
import { update } from '@/routes/admin/modules';

type Module = {
    id: string;
    name: string;
    description: string;
    version: string;
    vendor: string;
    icon: string;
    requires: { id: string; name: string }[];
    enabled: boolean;
    /** False when the plan leaves the module out. */
    available: boolean;
};

const icons: Record<string, LucideIcon> = {
    puzzle: Puzzle,
    'folder-kanban': FolderKanban,
    timer: Timer,
    receipt: Receipt,
};

export default function Modules({ modules }: { modules: Module[] }) {
    const { t } = useTranslation();
    const { errors } = usePage().props;
    const [processing, setProcessing] = useState<string | null>(null);

    const toggle = (module: Module) =>
        router.put(
            update.url(module.id),
            { enabled: !module.enabled },
            {
                preserveScroll: true,
                onStart: () => setProcessing(module.id),
                onFinish: () => setProcessing(null),
            },
        );

    return (
        <>
            <Head title={t('Modules')} />
            <AdminPageHeader
                title={t('Modules')}
                description={t(
                    'Optional features installed alongside the helpdesk. Turning one off hides it for everyone and keeps its data.',
                )}
            />

            <InputError message={errors?.module} className="mb-4" />

            {modules.length === 0 ? (
                <p className="rounded-xl border border-dashed p-8 text-center text-sm text-muted-foreground">
                    {t('No modules are installed.')}
                </p>
            ) : (
                <ul className="max-w-3xl space-y-3">
                    {modules.map((module) => {
                        const ModuleIcon = icons[module.icon] ?? Puzzle;

                        return (
                            <li
                                key={module.id}
                                className="flex items-start gap-4 rounded-xl border bg-card p-4 shadow-xs"
                            >
                                <span className="flex size-10 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                                    <ModuleIcon className="size-5" />
                                </span>
                                <div className="min-w-0 flex-1 space-y-1">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <h2 className="text-sm font-medium">
                                            {t(module.name)}
                                        </h2>
                                        <Badge variant="outline">
                                            v{module.version}
                                        </Badge>
                                        {!module.available ? (
                                            <Badge variant="secondary">
                                                {t('Not available')}
                                            </Badge>
                                        ) : (
                                            <Badge
                                                variant={
                                                    module.enabled
                                                        ? 'default'
                                                        : 'secondary'
                                                }
                                            >
                                                {module.enabled
                                                    ? t('Enabled')
                                                    : t('Disabled')}
                                            </Badge>
                                        )}
                                    </div>
                                    <p className="text-sm text-muted-foreground">
                                        {t(module.description)}
                                    </p>
                                    <p className="text-xs text-muted-foreground">
                                        {t('By :vendor', {
                                            vendor: module.vendor,
                                        })}
                                        {module.requires.length > 0 &&
                                            ` · ${t('Requires :modules', {
                                                modules: module.requires
                                                    .map(
                                                        (required) =>
                                                            required.name,
                                                    )
                                                    .join(', '),
                                            })}`}
                                    </p>
                                </div>
                                <Button
                                    variant={
                                        module.enabled ? 'secondary' : 'default'
                                    }
                                    size="sm"
                                    disabled={
                                        processing === module.id ||
                                        (!module.available && !module.enabled)
                                    }
                                    onClick={() => toggle(module)}
                                >
                                    {module.enabled
                                        ? t('Disable')
                                        : t('Enable')}
                                </Button>
                            </li>
                        );
                    })}
                </ul>
            )}
        </>
    );
}

Modules.layout = {
    breadcrumbs: [
        { title: 'Admin center', href: '/admin' },
        { title: 'Modules', href: '/admin/modules' },
    ],
};
