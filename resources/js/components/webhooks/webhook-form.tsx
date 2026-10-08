import { Form } from '@inertiajs/react';
import { useState } from 'react';
import { MultiSelectChips } from '@/components/admin/multi-select-chips';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTranslation } from '@/hooks/use-translation';
import { store, update } from '@/routes/admin/webhooks';
import type { Option } from '@/types';

export type WebhookData = {
    id: number;
    name: string;
    url: string;
    events: string[];
    is_active: boolean;
};

export function WebhookForm({
    webhook,
    events,
    onDone,
}: {
    webhook: WebhookData | null;
    events: Option[];
    onDone?: () => void;
}) {
    const { t } = useTranslation();
    const [selectedEvents, setSelectedEvents] = useState<string[]>(
        webhook?.events ?? events.map((event) => event.value),
    );
    const [active, setActive] = useState(webhook?.is_active ?? true);

    return (
        <Form
            {...(webhook ? update.form(webhook.id) : store.form())}
            transform={(data) => ({
                ...data,
                events: selectedEvents,
                is_active: active,
            })}
            onSuccess={onDone}
            options={{ preserveScroll: true }}
            className="space-y-4"
        >
            {({ errors, processing }) => (
                <>
                    <div className="grid gap-2">
                        <Label htmlFor="webhook-name">{t('Name')}</Label>
                        <Input
                            id="webhook-name"
                            name="name"
                            defaultValue={webhook?.name}
                            placeholder={t('e.g. Slack bridge')}
                            required
                        />
                        <InputError message={errors.name} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="webhook-url">{t('Endpoint URL')}</Label>
                        <Input
                            id="webhook-url"
                            name="url"
                            type="url"
                            defaultValue={webhook?.url}
                            placeholder="https://example.com/hooks/support"
                            required
                        />
                        <InputError message={errors.url} />
                    </div>
                    <div className="grid gap-2">
                        <Label>{t('Events')}</Label>
                        <MultiSelectChips
                            options={events}
                            value={selectedEvents}
                            onChange={setSelectedEvents}
                        />
                        <InputError message={errors.events} />
                    </div>
                    <label className="flex items-center gap-2 text-sm">
                        <input
                            type="checkbox"
                            checked={active}
                            onChange={(event) =>
                                setActive(event.target.checked)
                            }
                            className="size-4 accent-[var(--primary)]"
                        />
                        {t('Active')}
                    </label>
                    <div className="flex justify-end">
                        <Button type="submit" disabled={processing}>
                            {webhook ? t('Save') : t('Create webhook')}
                        </Button>
                    </div>
                </>
            )}
        </Form>
    );
}
