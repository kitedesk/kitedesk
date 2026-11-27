import { Head, router, usePage } from '@inertiajs/react';
import {
    FolderKanban,
    Puzzle,
    Receipt,
    Timer,
    TriangleAlert,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useState } from 'react';
import { AdminPageHeader } from '@/components/admin/page-header';
import InputError from '@/components/input-error';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
} from '@/components/ui/dialog';
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
    installed: boolean;
    /** A newer version than the installed one, which may bring database changes. */
    needs_update: boolean;
    /** Whether uninstalling deletes data. */
    has_data: boolean;
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
    const [uninstalling, setUninstalling] = useState<Module | null>(null);

    const run = (
        module: Module,
        action: 'install' | 'enable' | 'disable' | 'uninstall' | 'update',
        onDone?: () => void,
    ) =>
        router.put(
            update.url(module.id),
            action === 'uninstall' ? { action, confirm: true } : { action },
            {
                preserveScroll: true,
                onStart: () => setProcessing(module.id),
                onFinish: () => {
                    setProcessing(null);
                    onDone?.();
                },
            },
        );

    return (
        <>
            <Head title={t('Modules')} />
            <AdminPageHeader
                title={t('Modules')}
                description={t(
                    'Optional features that come with the helpdesk. Install one to set it up, then enable it. Disabling hides it for everyone and keeps its data; uninstalling removes its data.',
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
                                                {!module.installed
                                                    ? t('Not installed')
                                                    : module.enabled
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
                                <div className="flex shrink-0 flex-wrap justify-end gap-2">
                                    {!module.installed ? (
                                        <Button
                                            size="sm"
                                            disabled={
                                                processing === module.id ||
                                                !module.available
                                            }
                                            onClick={() =>
                                                run(module, 'install')
                                            }
                                        >
                                            {t('Install')}
                                        </Button>
                                    ) : (
                                        <>
                                            {module.needs_update && (
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    disabled={
                                                        processing === module.id
                                                    }
                                                    onClick={() =>
                                                        run(module, 'update')
                                                    }
                                                >
                                                    {t('Update')}
                                                </Button>
                                            )}
                                            <Button
                                                size="sm"
                                                variant={
                                                    module.enabled
                                                        ? 'secondary'
                                                        : 'default'
                                                }
                                                disabled={
                                                    processing === module.id ||
                                                    (!module.available &&
                                                        !module.enabled)
                                                }
                                                onClick={() =>
                                                    run(
                                                        module,
                                                        module.enabled
                                                            ? 'disable'
                                                            : 'enable',
                                                    )
                                                }
                                            >
                                                {module.enabled
                                                    ? t('Disable')
                                                    : t('Enable')}
                                            </Button>
                                            {!module.enabled && (
                                                <Button
                                                    size="sm"
                                                    variant="ghost"
                                                    disabled={
                                                        processing === module.id
                                                    }
                                                    onClick={() =>
                                                        setUninstalling(module)
                                                    }
                                                >
                                                    {t('Uninstall')}
                                                </Button>
                                            )}
                                        </>
                                    )}
                                </div>
                            </li>
                        );
                    })}
                </ul>
            )}

            <Dialog
                open={uninstalling !== null}
                onOpenChange={(open) => !open && setUninstalling(null)}
            >
                <DialogContent>
                    <DialogTitle>
                        {t('Uninstall :module?', {
                            module: t(uninstalling?.name ?? ''),
                        })}
                    </DialogTitle>
                    <DialogDescription>
                        {t(
                            'The module is set up again the next time you install it.',
                        )}
                    </DialogDescription>
                    {uninstalling?.has_data && (
                        <Alert variant="destructive">
                            <TriangleAlert />
                            <AlertTitle>
                                {t('This action is irreversible')}
                            </AlertTitle>
                            <AlertDescription>
                                {t(
                                    'Uninstalling permanently deletes the module’s tables and everything stored in them. This data cannot be recovered, even by installing the module again, which starts empty.',
                                )}
                            </AlertDescription>
                        </Alert>
                    )}
                    <DialogFooter>
                        <Button
                            variant="secondary"
                            onClick={() => setUninstalling(null)}
                        >
                            {t('Cancel')}
                        </Button>
                        <Button
                            variant="destructive"
                            disabled={processing !== null}
                            onClick={() =>
                                uninstalling &&
                                run(uninstalling, 'uninstall', () =>
                                    setUninstalling(null),
                                )
                            }
                        >
                            {t('Uninstall')}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

Modules.layout = {
    breadcrumbs: [
        { title: 'Admin center', href: '/admin' },
        { title: 'Modules', href: '/admin/modules' },
    ],
};
