import { Head, useForm } from '@inertiajs/react';
import { AlertTriangle, ImageUp, Plus, RotateCcw, Trash2 } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import type { CSSProperties, ReactNode } from 'react';
import { AdminPageHeader } from '@/components/admin/page-header';
import { TextField } from '@/components/admin/text-field';
import AppLogoIcon from '@/components/app-logo-icon';
import InputError from '@/components/input-error';
import { ActiveSwitch } from '@/components/sla/active-switch';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useEntitlements } from '@/hooks/use-entitlements';
import { useTranslation } from '@/hooks/use-translation';
import {
    brandVariables,
    contrastWithWhite,
    foregroundHex,
    isHexColor,
} from '@/lib/brand-color';
import { edit, update } from '@/routes/admin/branding';
import type { HeaderLink } from '@/types/branding';

type Settings = {
    name: string;
    primary_color: string | null;
    email_from_name: string | null;
    email_footer: string | null;
    help_title: string | null;
    help_subtitle: string | null;
    header_links: HeaderLink[];
    portal_footer: string | null;
    show_powered_by: boolean;
    custom_css: string | null;
};

type Assets = {
    logo: string | null;
    logo_dark: string | null;
    favicon: string | null;
};

type AssetKey = keyof Assets;

/** The stock indigo, offered as the starting point of the color picker. */
const DEFAULT_COLOR = '#4f46e5';

const MAX_HEADER_LINKS = 5;

export default function Branding({
    settings,
    assets,
    defaultSenderName,
}: {
    settings: Settings;
    assets: Assets;
    defaultSenderName: string;
}) {
    const { t } = useTranslation();
    const customBranding = useEntitlements().includes('custom_branding');
    const form = useForm({
        name: settings.name,
        primary_color: settings.primary_color ?? '',
        email_from_name: settings.email_from_name ?? '',
        email_footer: settings.email_footer ?? '',
        help_title: settings.help_title ?? '',
        help_subtitle: settings.help_subtitle ?? '',
        header_links: settings.header_links,
        portal_footer: settings.portal_footer ?? '',
        show_powered_by: settings.show_powered_by,
        custom_css: settings.custom_css ?? '',
        logo: null as File | null,
        logo_dark: null as File | null,
        favicon: null as File | null,
        remove_logo: false,
        remove_logo_dark: false,
        remove_favicon: false,
    });
    const errors = form.errors as Record<string, string | undefined>;

    const color = isHexColor(form.data.primary_color)
        ? form.data.primary_color
        : null;
    const lowContrast = color !== null && contrastWithWhite(color) < 3;

    const logoPreview = useAssetPreview(
        form.data.logo,
        form.data.remove_logo ? null : assets.logo,
    );

    const setAsset = (key: AssetKey, file: File | null) =>
        form.setData((data) => ({
            ...data,
            [key]: file,
            [`remove_${key}`]: file === null,
        }));

    const setLink = (index: number, link: Partial<HeaderLink>) =>
        form.setData(
            'header_links',
            form.data.header_links.map((current, position) =>
                position === index ? { ...current, ...link } : current,
            ),
        );

    return (
        <>
            <Head title={t('Branding')} />
            <AdminPageHeader
                title={t('Branding')}
                description={t(
                    'Make the help center, the agent workspace and emails look like your company.',
                )}
            />

            <form
                onSubmit={(event) => {
                    event.preventDefault();
                    form.post(update.url(), {
                        forceFormData: true,
                        preserveScroll: true,
                        onSuccess: () =>
                            form.reset(
                                'logo',
                                'logo_dark',
                                'favicon',
                                'remove_logo',
                                'remove_logo_dark',
                                'remove_favicon',
                            ),
                    });
                }}
                className="max-w-3xl space-y-6"
            >
                <Section
                    title={t('General')}
                    description={t(
                        'Shown in the browser tab, the sidebar, sign-in pages and the help center.',
                    )}
                >
                    <TextField
                        id="brand-name"
                        label={t('Name')}
                        value={form.data.name}
                        onChange={(name) => form.setData('name', name)}
                        error={errors.name}
                        required
                    />
                    <div className="grid gap-4 sm:grid-cols-2">
                        <AssetField
                            id="brand-logo"
                            label={t('Logo')}
                            hint={t(
                                'PNG, JPG, WebP or SVG, up to 1 MB. Replaces the name next to the icon.',
                            )}
                            accept=".png,.jpg,.jpeg,.webp,.svg"
                            file={form.data.logo}
                            current={form.data.remove_logo ? null : assets.logo}
                            onChange={(file) => setAsset('logo', file)}
                            error={errors.logo}
                        />
                        <AssetField
                            id="brand-logo-dark"
                            label={t('Logo for dark mode')}
                            hint={t(
                                'Optional. Used on dark backgrounds instead of the logo.',
                            )}
                            accept=".png,.jpg,.jpeg,.webp,.svg"
                            file={form.data.logo_dark}
                            current={
                                form.data.remove_logo_dark
                                    ? null
                                    : assets.logo_dark
                            }
                            onChange={(file) => setAsset('logo_dark', file)}
                            error={errors.logo_dark}
                            dark
                        />
                        <AssetField
                            id="brand-favicon"
                            label={t('Favicon')}
                            hint={t(
                                'The icon in the browser tab. Square PNG, ICO or SVG, up to 256 KB.',
                            )}
                            accept=".png,.ico,.svg"
                            file={form.data.favicon}
                            current={
                                form.data.remove_favicon ? null : assets.favicon
                            }
                            onChange={(file) => setAsset('favicon', file)}
                            error={errors.favicon}
                            small
                        />
                    </div>
                </Section>

                <Section
                    title={t('Colors')}
                    description={t(
                        'Used for buttons, links, highlights and focus outlines. A matching shade is picked for dark mode.',
                    )}
                >
                    <div className="flex flex-wrap items-end gap-3">
                        <div className="grid gap-2">
                            <Label htmlFor="brand-color">
                                {t('Brand color')}
                            </Label>
                            <div className="flex items-center gap-2">
                                <input
                                    type="color"
                                    aria-label={t('Brand color')}
                                    value={color ?? DEFAULT_COLOR}
                                    onChange={(event) =>
                                        form.setData(
                                            'primary_color',
                                            event.target.value,
                                        )
                                    }
                                    className="h-9 w-12 cursor-pointer rounded-md border bg-transparent p-1"
                                />
                                <Input
                                    id="brand-color"
                                    value={form.data.primary_color}
                                    placeholder={t('Default')}
                                    onChange={(event) =>
                                        form.setData(
                                            'primary_color',
                                            event.target.value.trim(),
                                        )
                                    }
                                    className="w-32 font-mono"
                                    spellCheck={false}
                                />
                            </div>
                        </div>
                        {form.data.primary_color !== '' && (
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                onClick={() =>
                                    form.setData('primary_color', '')
                                }
                            >
                                <RotateCcw /> {t('Reset to default')}
                            </Button>
                        )}
                    </div>
                    <InputError message={errors.primary_color} />
                    {lowContrast && (
                        <p className="flex items-start gap-2 rounded-md border border-amber-500/40 bg-amber-500/10 px-3 py-2 text-sm text-amber-700 dark:text-amber-300">
                            <AlertTriangle className="mt-0.5 size-4 shrink-0" />
                            {t(
                                'This color is light, so links and outlines may be hard to see on a white background.',
                            )}
                        </p>
                    )}
                    <div className="grid gap-3 sm:grid-cols-2">
                        <ColorPreview color={color} name={form.data.name} />
                        <ColorPreview
                            color={color}
                            name={form.data.name}
                            dark
                        />
                    </div>
                </Section>

                <Section
                    title={t('Email')}
                    description={t(
                        'Ticket emails and notifications show your logo, color and footer.',
                    )}
                >
                    <div className="grid gap-4 sm:grid-cols-2">
                        <TextField
                            id="brand-sender"
                            label={t('Sender name')}
                            value={form.data.email_from_name}
                            onChange={(value) =>
                                form.setData('email_from_name', value)
                            }
                            placeholder={form.data.name || defaultSenderName}
                            error={errors.email_from_name}
                        />
                    </div>
                    <p className="-mt-2 text-xs text-muted-foreground">
                        {t(
                            'Used when the mailbox a ticket came from has no name of its own.',
                        )}
                    </p>
                    <TextField
                        id="brand-email-footer"
                        label={t('Footer text')}
                        value={form.data.email_footer}
                        onChange={(value) =>
                            form.setData('email_footer', value)
                        }
                        placeholder={t(
                            'e.g. Acme Inc. · 123 Main Street · Support hours 9–18',
                        )}
                        error={errors.email_footer}
                        multiline
                    />
                    <EmailPreview
                        name={form.data.name}
                        logo={
                            logoPreview && !isSvg(form.data.logo, assets.logo)
                                ? logoPreview
                                : null
                        }
                        color={color ?? DEFAULT_COLOR}
                        footer={form.data.email_footer}
                    />
                </Section>

                <Section
                    title={t('Help center')}
                    description={t(
                        'What customers see on the help center and their requests.',
                    )}
                >
                    <div className="grid gap-4 sm:grid-cols-2">
                        <TextField
                            id="brand-help-title"
                            label={t('Heading')}
                            value={form.data.help_title}
                            onChange={(value) =>
                                form.setData('help_title', value)
                            }
                            placeholder={t('How can we help?')}
                            error={errors.help_title}
                        />
                        <TextField
                            id="brand-help-subtitle"
                            label={t('Subheading')}
                            value={form.data.help_subtitle}
                            onChange={(value) =>
                                form.setData('help_subtitle', value)
                            }
                            placeholder={t(
                                'Search our guides or browse by topic.',
                            )}
                            error={errors.help_subtitle}
                        />
                    </div>

                    <div className="grid gap-2">
                        <Label>{t('Header links')}</Label>
                        <p className="text-xs text-muted-foreground">
                            {t(
                                'Links to your website or status page, shown next to the help center navigation.',
                            )}
                        </p>
                        {form.data.header_links.map((link, index) => (
                            <div key={index} className="grid gap-1">
                                <div className="flex gap-2">
                                    <Input
                                        aria-label={t('Label')}
                                        placeholder={t('Label')}
                                        value={link.label}
                                        onChange={(event) =>
                                            setLink(index, {
                                                label: event.target.value,
                                            })
                                        }
                                        className="w-40"
                                    />
                                    <Input
                                        aria-label={t('URL')}
                                        placeholder="https://"
                                        value={link.url}
                                        onChange={(event) =>
                                            setLink(index, {
                                                url: event.target.value,
                                            })
                                        }
                                    />
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        aria-label={t('Remove')}
                                        onClick={() =>
                                            form.setData(
                                                'header_links',
                                                form.data.header_links.filter(
                                                    (_, position) =>
                                                        position !== index,
                                                ),
                                            )
                                        }
                                    >
                                        <Trash2 />
                                    </Button>
                                </div>
                                <InputError
                                    message={
                                        errors[`header_links.${index}.label`] ??
                                        errors[`header_links.${index}.url`]
                                    }
                                />
                            </div>
                        ))}
                        <InputError message={errors.header_links} />
                        {form.data.header_links.length < MAX_HEADER_LINKS && (
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                className="justify-self-start"
                                onClick={() =>
                                    form.setData('header_links', [
                                        ...form.data.header_links,
                                        { label: '', url: '' },
                                    ])
                                }
                            >
                                <Plus /> {t('Add link')}
                            </Button>
                        )}
                    </div>

                    <TextField
                        id="brand-portal-footer"
                        label={t('Footer text')}
                        value={form.data.portal_footer}
                        onChange={(value) =>
                            form.setData('portal_footer', value)
                        }
                        error={errors.portal_footer}
                        multiline
                    />

                    <div className="flex items-center justify-between gap-4 rounded-lg border p-3">
                        <div>
                            <p className="text-sm font-medium">
                                {t('Show "Powered by KiteDesk"')}
                            </p>
                            <p className="text-xs text-muted-foreground">
                                {t('A small credit in the help center footer.')}
                            </p>
                        </div>
                        <ActiveSwitch
                            checked={form.data.show_powered_by}
                            onChange={(checked) =>
                                form.setData('show_powered_by', checked)
                            }
                            label={t('Show "Powered by KiteDesk"')}
                            disabled={!customBranding}
                        />
                    </div>
                    <InputError message={errors.show_powered_by} />

                    <div className="grid gap-2">
                        <Label htmlFor="brand-css">{t('Custom CSS')}</Label>
                        <textarea
                            id="brand-css"
                            value={form.data.custom_css}
                            onChange={(event) =>
                                form.setData('custom_css', event.target.value)
                            }
                            rows={6}
                            spellCheck={false}
                            placeholder=".help-hero { … }"
                            disabled={!customBranding}
                            className="w-full rounded-md border bg-transparent px-3 py-2 font-mono text-xs shadow-xs outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-50 dark:bg-input/30"
                        />
                        <p className="text-xs text-muted-foreground">
                            {customBranding
                                ? t(
                                      'Applied to the help center and customer pages only, never to the agent workspace. @import is not allowed.',
                                  )
                                : t(
                                      'Your plan does not include custom CSS or hiding "Powered by KiteDesk".',
                                  )}
                        </p>
                        <InputError message={errors.custom_css} />
                    </div>
                </Section>

                <div className="flex justify-end">
                    <Button type="submit" disabled={form.processing}>
                        {t('Save')}
                    </Button>
                </div>
            </form>
        </>
    );
}

Branding.layout = {
    breadcrumbs: [
        { title: 'Admin center', href: '/admin' },
        { title: 'Branding', href: edit() },
    ],
};

function Section({
    title,
    description,
    children,
}: {
    title: string;
    description: string;
    children: ReactNode;
}) {
    return (
        <section className="space-y-4 rounded-xl border bg-card p-5 shadow-xs">
            <div>
                <h2 className="font-semibold">{title}</h2>
                <p className="text-sm text-muted-foreground">{description}</p>
            </div>
            {children}
        </section>
    );
}

/**
 * A URL for showing a picked file before it is uploaded, or the stored file.
 */
function useAssetPreview(file: File | null, current: string | null) {
    const [url, setUrl] = useState<string | null>(null);

    useEffect(() => {
        if (!file) {
            setUrl(null);

            return;
        }

        const objectUrl = URL.createObjectURL(file);
        setUrl(objectUrl);

        return () => URL.revokeObjectURL(objectUrl);
    }, [file]);

    return file ? url : current;
}

function isSvg(file: File | null, current: string | null): boolean {
    if (file) {
        return file.name.toLowerCase().endsWith('.svg');
    }

    return current?.split('?')[0].toLowerCase().endsWith('.svg') ?? false;
}

function AssetField({
    id,
    label,
    hint,
    accept,
    file,
    current,
    onChange,
    error,
    dark = false,
    small = false,
}: {
    id: string;
    label: string;
    hint: string;
    accept: string;
    file: File | null;
    current: string | null;
    onChange: (file: File | null) => void;
    error?: string;
    dark?: boolean;
    small?: boolean;
}) {
    const { t } = useTranslation();
    const input = useRef<HTMLInputElement>(null);
    const preview = useAssetPreview(file, current);

    return (
        <div className="grid content-start gap-2">
            <Label htmlFor={id}>{label}</Label>
            <div
                className={
                    dark
                        ? 'dark flex h-20 items-center justify-center rounded-lg border bg-neutral-900 p-3'
                        : 'flex h-20 items-center justify-center rounded-lg border bg-muted/30 p-3'
                }
            >
                {preview ? (
                    <img
                        src={preview}
                        alt=""
                        className={
                            small
                                ? 'size-8 object-contain'
                                : 'max-h-full max-w-full object-contain'
                        }
                    />
                ) : (
                    <span className="text-xs text-muted-foreground">
                        {t('None')}
                    </span>
                )}
            </div>
            <input
                ref={input}
                id={id}
                type="file"
                accept={accept}
                className="sr-only"
                onChange={(event) => {
                    onChange(event.target.files?.[0] ?? null);
                    event.target.value = '';
                }}
            />
            <div className="flex gap-2">
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    onClick={() => input.current?.click()}
                >
                    <ImageUp /> {preview ? t('Replace') : t('Upload')}
                </Button>
                {preview && (
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        onClick={() => onChange(null)}
                    >
                        <Trash2 /> {t('Remove')}
                    </Button>
                )}
            </div>
            <p className="text-xs text-muted-foreground">{hint}</p>
            <InputError message={error} />
        </div>
    );
}

/**
 * A few themed elements drawn with the chosen color, in light or dark mode.
 */
function ColorPreview({
    color,
    name,
    dark = false,
}: {
    color: string | null;
    name: string;
    dark?: boolean;
}) {
    const { t } = useTranslation();
    const style = useMemo(
        () =>
            (color
                ? brandVariables(color)[dark ? 'dark' : 'light']
                : {}) as CSSProperties,
        [color, dark],
    );

    return (
        <div
            style={style}
            className={`${dark ? 'dark' : ''} space-y-3 rounded-lg border bg-background p-4 text-foreground`}
        >
            <div className="flex items-center gap-2 rounded-md bg-sidebar p-2">
                <span className="flex size-7 items-center justify-center rounded-md bg-sidebar-primary text-sidebar-primary-foreground">
                    <AppLogoIcon className="size-4 fill-current" />
                </span>
                <span className="truncate text-sm font-semibold">{name}</span>
            </div>
            <div className="flex flex-wrap items-center gap-3">
                <Button type="button" size="sm" tabIndex={-1}>
                    {t('Submit a request')}
                </Button>
                <span className="text-sm font-medium text-primary underline underline-offset-4">
                    {t('Link')}
                </span>
                <span className="rounded-full bg-primary/10 px-2 py-0.5 text-xs font-medium text-primary">
                    {t('Open')}
                </span>
            </div>
            <div className="rounded-md border px-3 py-1.5 text-sm text-muted-foreground ring-[3px] ring-ring/50">
                {t('Focused field')}
            </div>
        </div>
    );
}

function EmailPreview({
    name,
    logo,
    color,
    footer,
}: {
    name: string;
    logo: string | null;
    color: string;
    footer: string;
}) {
    const { t } = useTranslation();

    return (
        <div className="rounded-lg border bg-[#f4f4f7] p-4 text-[#1f2937]">
            <div className="mb-3 text-center">
                {logo ? (
                    <img src={logo} alt="" className="mx-auto h-8 w-auto" />
                ) : (
                    <span className="text-sm font-semibold">{name}</span>
                )}
            </div>
            <div className="space-y-3 rounded-lg border border-[#e5e7eb] bg-white p-4 text-sm">
                <p>{t('Hi Jane, we replied to your request.')}</p>
                <span
                    className="inline-block rounded-md px-3 py-1.5 text-xs font-semibold"
                    style={{
                        backgroundColor: color,
                        color: foregroundHex(color),
                    }}
                >
                    {t('View request')}
                </span>
            </div>
            <p className="mt-3 text-center text-xs whitespace-pre-line text-[#9ca3af]">
                {t('Request :number', { number: '#42' })} · {name}
                {footer && `\n${footer}`}
            </p>
        </div>
    );
}
