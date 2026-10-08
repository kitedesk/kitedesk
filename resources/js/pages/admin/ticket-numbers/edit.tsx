import { Head, useForm } from '@inertiajs/react';
import { Hash } from 'lucide-react';
import { useMemo, useRef } from 'react';
import { AdminPageHeader } from '@/components/admin/page-header';
import InputError from '@/components/input-error';
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
import { useTranslation } from '@/hooks/use-translation';
import { edit, update } from '@/routes/admin/ticket-numbers';
import type { Option } from '@/types';

type Settings = { format: string; reset: string; next: number };

const TOKENS = /\{(seq|random)(?::(\d{1,2}))?\}|\{(yyyy|yy|mm|dd)\}/g;

const PRESETS: { label: string; format: string; reset: string }[] = [
    { label: '42', format: '{seq}', reset: 'never' },
    { label: 'TKT-00042', format: 'TKT-{seq:5}', reset: 'never' },
    { label: '2026-0042', format: '{yyyy}-{seq:4}', reset: 'yearly' },
    { label: '48210937', format: '{random:8}', reset: 'never' },
];

const TOKEN_HELP: { token: string; description: string }[] = [
    { token: '{seq}', description: 'Next number in the sequence' },
    { token: '{seq:5}', description: 'Sequence padded with zeros to 5 digits' },
    { token: '{random:8}', description: '8 random digits' },
    { token: '{yyyy}', description: 'Year (2026)' },
    { token: '{yy}', description: 'Short year (26)' },
    { token: '{mm}', description: 'Month (01–12)' },
    { token: '{dd}', description: 'Day (01–31)' },
];

/**
 * What a ticket created now would be called, mirroring TicketNumberFormat on the server.
 */
function render(format: string, sequence: number, at: Date): string {
    const pad = (value: number, width: number) =>
        String(value).padStart(width, '0');

    return format.replace(
        TOKENS,
        (_, kind: string | undefined, width: string | undefined, date) => {
            if (kind === 'seq') {
                return pad(sequence, Number(width ?? 0));
            }

            if (kind === 'random') {
                const length = Number(width ?? 6);

                return (
                    String(1 + Math.floor(Math.random() * 9)) +
                    Array.from({ length: length - 1 }, () =>
                        Math.floor(Math.random() * 10),
                    ).join('')
                );
            }

            return {
                yyyy: String(at.getFullYear()),
                yy: String(at.getFullYear()).slice(-2),
                mm: pad(at.getMonth() + 1, 2),
                dd: pad(at.getDate(), 2),
            }[date as 'yyyy' | 'yy' | 'mm' | 'dd'];
        },
    );
}

/**
 * Plain numbers are shown as "#42"; formatted ones as they are.
 */
function reference(number: string): string {
    return /^\d+$/.test(number) ? `#${number}` : number;
}

export default function TicketNumbers({
    settings,
    resets,
    latest,
}: {
    settings: Settings;
    resets: Option[];
    latest: string | null;
}) {
    const { t } = useTranslation();
    const input = useRef<HTMLInputElement>(null);
    const form = useForm(settings);
    const usesSequence = form.data.format.includes('{seq');

    const preview = useMemo(() => {
        const now = new Date();

        return [0, 1, 2].map((offset) =>
            reference(render(form.data.format, form.data.next + offset, now)),
        );
    }, [form.data.format, form.data.next]);

    const insert = (token: string) => {
        const element = input.current;
        const start = element?.selectionStart ?? form.data.format.length;
        const end = element?.selectionEnd ?? start;

        form.setData(
            'format',
            form.data.format.slice(0, start) +
                token +
                form.data.format.slice(end),
        );

        window.requestAnimationFrame(() => {
            element?.focus();
            element?.setSelectionRange(
                start + token.length,
                start + token.length,
            );
        });
    };

    return (
        <>
            <Head title={t('Ticket numbers')} />
            <AdminPageHeader
                title={t('Ticket numbers')}
                description={t(
                    'Choose how new tickets are numbered. Existing tickets keep their numbers, and old numbers still work in search and email replies.',
                )}
            />

            <form
                onSubmit={(event) => {
                    event.preventDefault();
                    form.put(update.url(), { preserveScroll: true });
                }}
                className="grid max-w-3xl gap-8 lg:grid-cols-[minmax(0,1fr)_16rem]"
            >
                <div className="space-y-6">
                    <div className="grid gap-2">
                        <Label>{t('Start from an example')}</Label>
                        <div className="flex flex-wrap gap-2">
                            {PRESETS.map((preset) => (
                                <Button
                                    key={preset.format}
                                    type="button"
                                    variant={
                                        form.data.format === preset.format
                                            ? 'secondary'
                                            : 'outline'
                                    }
                                    size="sm"
                                    className="font-mono"
                                    onClick={() =>
                                        form.setData({
                                            ...form.data,
                                            format: preset.format,
                                            reset: preset.reset,
                                        })
                                    }
                                >
                                    {preset.label}
                                </Button>
                            ))}
                        </div>
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="number-format">{t('Format')}</Label>
                        <Input
                            ref={input}
                            id="number-format"
                            value={form.data.format}
                            onChange={(event) =>
                                form.setData('format', event.target.value)
                            }
                            className="font-mono"
                            autoComplete="off"
                            spellCheck={false}
                        />
                        <InputError message={form.errors.format} />
                        <ul className="grid gap-1 rounded-lg border bg-muted/30 p-3 text-xs sm:grid-cols-2">
                            {TOKEN_HELP.map((help) => (
                                <li key={help.token}>
                                    <button
                                        type="button"
                                        onClick={() => insert(help.token)}
                                        className="rounded bg-background px-1.5 py-0.5 font-mono text-foreground ring-1 ring-border hover:ring-primary"
                                    >
                                        {help.token}
                                    </button>{' '}
                                    <span className="text-muted-foreground">
                                        {t(help.description)}
                                    </span>
                                </li>
                            ))}
                        </ul>
                        <p className="text-xs text-muted-foreground">
                            {t(
                                'Letters, numbers, - _ . / are kept as typed. Include {seq} or {random} so every ticket gets its own number.',
                            )}
                        </p>
                    </div>

                    {usesSequence && (
                        <div className="grid gap-4 sm:grid-cols-2">
                            <div className="grid gap-2">
                                <Label htmlFor="number-next">
                                    {t('Next sequence number')}
                                </Label>
                                <Input
                                    id="number-next"
                                    type="number"
                                    min={1}
                                    value={form.data.next}
                                    onChange={(event) =>
                                        form.setData(
                                            'next',
                                            Number(event.target.value),
                                        )
                                    }
                                />
                                <InputError message={form.errors.next} />
                            </div>
                            <div className="grid gap-2">
                                <Label>{t('Restart the sequence')}</Label>
                                <Select
                                    value={form.data.reset}
                                    onValueChange={(reset) =>
                                        form.setData('reset', reset)
                                    }
                                >
                                    <SelectTrigger className="w-full">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {resets.map((option) => (
                                            <SelectItem
                                                key={option.value}
                                                value={option.value}
                                            >
                                                {option.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError message={form.errors.reset} />
                            </div>
                        </div>
                    )}

                    <div className="flex justify-end">
                        <Button type="submit" disabled={form.processing}>
                            {t('Save')}
                        </Button>
                    </div>
                </div>

                <aside className="space-y-3 self-start rounded-xl border bg-card p-4 shadow-xs">
                    <p className="flex items-center gap-1.5 text-xs font-medium tracking-wide text-muted-foreground uppercase">
                        <Hash className="size-3.5" /> {t('Preview')}
                    </p>
                    <ol className="space-y-1.5 font-mono text-sm">
                        {preview.map((number, index) => (
                            <li
                                key={index}
                                className={
                                    index === 0
                                        ? 'font-semibold'
                                        : 'text-muted-foreground'
                                }
                            >
                                {number}
                            </li>
                        ))}
                    </ol>
                    {latest && (
                        <p className="border-t pt-3 text-xs text-muted-foreground">
                            {t('Latest ticket: :number', { number: latest })}
                        </p>
                    )}
                </aside>
            </form>
        </>
    );
}

TicketNumbers.layout = {
    breadcrumbs: [
        { title: 'Admin center', href: '/admin' },
        { title: 'Ticket numbers', href: edit() },
    ],
};
