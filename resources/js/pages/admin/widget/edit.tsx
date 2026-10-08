import { Head, useForm } from '@inertiajs/react';
import { Check, Copy } from 'lucide-react';
import { AdminPageHeader } from '@/components/admin/page-header';
import { TextField } from '@/components/admin/text-field';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useClipboard } from '@/hooks/use-clipboard';
import { useTranslation } from '@/hooks/use-translation';
import { edit, update } from '@/routes/admin/widget';

type Settings = {
    enabled: boolean;
    allowed_domains: string[];
    position: 'right' | 'left';
    launcher_label: string | null;
    greeting: string | null;
};

export default function WidgetSettings({
    settings,
    scriptUrl,
}: {
    settings: Settings;
    scriptUrl: string;
}) {
    const { t } = useTranslation();
    const [copied, copy] = useClipboard();
    const form = useForm({
        enabled: settings.enabled,
        allowed_domains: settings.allowed_domains.join('\n'),
        position: settings.position,
        launcher_label: settings.launcher_label ?? '',
        greeting: settings.greeting ?? '',
    });
    const snippet = `<script src="${scriptUrl}" async></script>`;
    const errors = form.errors as Record<string, string | undefined>;
    const domainError =
        errors.allowed_domains ??
        Object.entries(errors).find(([key]) =>
            key.startsWith('allowed_domains.'),
        )?.[1];

    return (
        <>
            <Head title={t('Website widget')} />
            <AdminPageHeader
                title={t('Website widget')}
                description={t(
                    'Add a help button to your website. Visitors open tickets from it without leaving the page, and follow them by email.',
                )}
            />

            <form
                onSubmit={(event) => {
                    event.preventDefault();
                    form.transform((data) => ({
                        ...data,
                        allowed_domains: data.allowed_domains
                            .split('\n')
                            .map((domain) => domain.trim())
                            .filter(Boolean),
                    }));
                    form.put(update.url(), { preserveScroll: true });
                }}
                className="max-w-2xl space-y-6"
            >
                <div className="grid gap-2">
                    <Label className="flex items-start gap-3 font-normal">
                        <Checkbox
                            checked={form.data.enabled}
                            onCheckedChange={(checked) =>
                                form.setData('enabled', checked === true)
                            }
                            className="mt-0.5"
                        />
                        <span className="space-y-1">
                            <span className="block font-medium">
                                {t('Turn on the website widget')}
                            </span>
                            <span className="block text-sm text-muted-foreground">
                                {t(
                                    'While it is off, the code below shows nothing.',
                                )}
                            </span>
                        </span>
                    </Label>
                    <InputError message={form.errors.enabled} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="widget-snippet">
                        {t('Code for your website')}
                    </Label>
                    <div className="flex items-start gap-2">
                        <code
                            id="widget-snippet"
                            className="flex-1 rounded-md border bg-muted/50 px-3 py-2 font-mono text-xs break-all"
                        >
                            {snippet}
                        </code>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={() => void copy(snippet)}
                        >
                            {copied === snippet ? <Check /> : <Copy />}
                            {copied === snippet ? t('Copied') : t('Copy')}
                        </Button>
                    </div>
                    <p className="text-xs text-muted-foreground">
                        {t(
                            'Paste it before </body> on every page that should show the widget. To fill in the name and email of someone signed in to your site, call window.KiteDesk.identify({ name, email }).',
                        )}
                    </p>
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="widget-domains">
                        {t('Websites allowed to show it')}
                    </Label>
                    <textarea
                        id="widget-domains"
                        rows={3}
                        value={form.data.allowed_domains}
                        onChange={(event) =>
                            form.setData('allowed_domains', event.target.value)
                        }
                        placeholder={'example.com\n*.example.com'}
                        className="w-full rounded-md border bg-transparent px-3 py-2 font-mono text-sm shadow-xs outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30"
                    />
                    <p className="text-xs text-muted-foreground">
                        {t(
                            'One domain per line. Leave it empty to allow any website.',
                        )}
                    </p>
                    <InputError message={domainError} />
                </div>

                <div className="grid gap-4 sm:grid-cols-2">
                    <TextField
                        id="widget-label"
                        label={t('Button text')}
                        value={form.data.launcher_label}
                        onChange={(value) =>
                            form.setData('launcher_label', value)
                        }
                        placeholder={t('Help')}
                        error={form.errors.launcher_label}
                    />
                    <div className="grid gap-2">
                        <Label htmlFor="widget-position">
                            {t('Button position')}
                        </Label>
                        <Select
                            value={form.data.position}
                            onValueChange={(value) =>
                                form.setData(
                                    'position',
                                    value as Settings['position'],
                                )
                            }
                        >
                            <SelectTrigger id="widget-position">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="right">
                                    {t('Bottom right')}
                                </SelectItem>
                                <SelectItem value="left">
                                    {t('Bottom left')}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError message={form.errors.position} />
                    </div>
                </div>

                <TextField
                    id="widget-greeting"
                    label={t('Greeting')}
                    value={form.data.greeting}
                    onChange={(value) => form.setData('greeting', value)}
                    placeholder={t(
                        "Tell us what's going on and we'll get back to you by email.",
                    )}
                    error={form.errors.greeting}
                    multiline
                />

                <p className="text-xs text-muted-foreground">
                    {t(
                        'The widget uses the colors and name from Branding, and the categories and fields customers see in the portal.',
                    )}
                </p>

                <div className="flex justify-end">
                    <Button type="submit" disabled={form.processing}>
                        {t('Save')}
                    </Button>
                </div>
            </form>
        </>
    );
}

WidgetSettings.layout = {
    breadcrumbs: [
        { title: 'Admin center', href: '/admin' },
        { title: 'Website widget', href: edit() },
    ],
};
