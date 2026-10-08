import { Head, useForm } from '@inertiajs/react';
import { Mail } from 'lucide-react';
import { AdminPageHeader } from '@/components/admin/page-header';
import InputError from '@/components/input-error';
import { StarRating } from '@/components/tickets/star-rating';
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
import { useTranslation } from '@/hooks/use-translation';
import { edit, update } from '@/routes/admin/satisfaction';

type Settings = { enabled: boolean; delay_hours: number };

export default function SatisfactionSettings({
    settings,
    enabledSince,
    delays,
}: {
    settings: Settings;
    enabledSince: string | null;
    delays: number[];
}) {
    const { t, localeTag } = useTranslation();
    const form = useForm(settings);

    const delayLabel = (hours: number) =>
        hours === 0
            ? t('Right after it is solved')
            : t(':hours hours after it is solved', { hours });

    return (
        <>
            <Head title={t('Satisfaction survey')} />
            <AdminPageHeader
                title={t('Satisfaction survey')}
                description={t(
                    'After a ticket is solved, email the requester a one-click survey to rate the support from 1 to 5 stars. They can also rate from the portal. Each ticket is surveyed once.',
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
                                    {t('Send the satisfaction survey')}
                                </span>
                                <span className="block text-sm text-muted-foreground">
                                    {enabledSince && settings.enabled
                                        ? t(
                                              'On since :date. Tickets solved before then are not surveyed.',
                                              {
                                                  date: new Date(
                                                      enabledSince,
                                                  ).toLocaleDateString(
                                                      localeTag,
                                                  ),
                                              },
                                          )
                                        : t(
                                              'Only tickets solved after you turn it on are surveyed.',
                                          )}
                                </span>
                            </span>
                        </Label>
                        <InputError message={form.errors.enabled} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="satisfaction-delay">
                            {t('When to send it')}
                        </Label>
                        <Select
                            value={String(form.data.delay_hours)}
                            onValueChange={(value) =>
                                form.setData('delay_hours', Number(value))
                            }
                            disabled={!form.data.enabled}
                        >
                            <SelectTrigger
                                id="satisfaction-delay"
                                className="w-full sm:w-72"
                            >
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {delays.map((hours) => (
                                    <SelectItem
                                        key={hours}
                                        value={String(hours)}
                                    >
                                        {delayLabel(hours)}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <p className="text-xs text-muted-foreground">
                            {t(
                                'A delay gives the customer time to reopen the ticket if the problem comes back. If it is reopened before then, the survey waits until it is solved again.',
                            )}
                        </p>
                        <InputError message={form.errors.delay_hours} />
                    </div>

                    <div className="flex justify-end">
                        <Button type="submit" disabled={form.processing}>
                            {t('Save')}
                        </Button>
                    </div>
                </div>

                <aside className="space-y-3 self-start rounded-xl border bg-card p-4 shadow-xs">
                    <p className="flex items-center gap-1.5 text-xs font-medium tracking-wide text-muted-foreground uppercase">
                        <Mail className="size-3.5" /> {t('Preview')}
                    </p>
                    <p className="text-sm">
                        {t('How would you rate the support you received?')}
                    </p>
                    <StarRating value={4} size="md" />
                    <p className="text-xs text-muted-foreground">
                        {t(
                            'Clicking a star saves the rating and opens a page to add a comment.',
                        )}
                    </p>
                </aside>
            </form>
        </>
    );
}

SatisfactionSettings.layout = {
    breadcrumbs: [
        { title: 'Admin center', href: '/admin' },
        { title: 'Satisfaction survey', href: edit() },
    ],
};
