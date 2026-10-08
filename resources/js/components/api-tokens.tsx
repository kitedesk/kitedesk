import { Form, router } from '@inertiajs/react';
import { Check, Copy, KeyRound, Trash2 } from 'lucide-react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
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
import { formatDateTime, relativeTime } from '@/lib/tickets';

export type ApiToken = {
    id: number;
    name: string;
    abilities: string[];
    owner: { id: number; name: string; email: string } | null;
    created_by: { id: number; name: string } | null;
    last_used_at: string | null;
    expires_at: string | null;
    created_at: string;
    destroy_url: string;
};

export type ApiAbilityOption = { value: string; label: string };

/** Someone a token can be created for, with the abilities their role allows. */
export type TokenOwner = {
    id: number;
    name: string;
    email: string;
    abilities: string[];
};

export type NewApiToken = { name: string; plainText: string };

/**
 * The form for a new token. With several `owners` it asks who the token acts as; abilities the
 * chosen person's role doesn't allow are shown but can't be picked.
 */
export function CreateApiTokenForm({
    form,
    owners,
    abilities,
    expiryDays,
}: {
    /** Where the form posts, e.g. `Controller.store.form()`. */
    form: { action: string; method: 'post' };
    owners: TokenOwner[];
    abilities: ApiAbilityOption[];
    expiryDays: number[];
}) {
    const { t } = useTranslation();
    const [ownerId, setOwnerId] = useState(
        owners[0] ? String(owners[0].id) : '',
    );
    const allowed =
        owners.find((owner) => String(owner.id) === ownerId)?.abilities ?? [];
    const choosesOwner = owners.length > 1;

    return (
        <Form
            {...form}
            options={{ preserveScroll: true }}
            resetOnSuccess
            className="space-y-5"
        >
            {({ errors, processing }) => (
                <>
                    <div
                        className={
                            choosesOwner
                                ? 'grid gap-4 sm:grid-cols-3'
                                : 'grid gap-4 sm:grid-cols-2'
                        }
                    >
                        <div className="grid gap-2">
                            <Label htmlFor="name">{t('Name')}</Label>
                            <Input
                                id="name"
                                name="name"
                                placeholder={t('e.g. CRM sync')}
                                required
                            />
                            <InputError message={errors.name} />
                        </div>
                        {choosesOwner ? (
                            <div className="grid gap-2">
                                <Label>{t('Acts as')}</Label>
                                <Select
                                    name="user_id"
                                    value={ownerId}
                                    onValueChange={setOwnerId}
                                >
                                    <SelectTrigger className="w-full">
                                        <SelectValue
                                            placeholder={t(
                                                'Select a team member',
                                            )}
                                        />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {owners.map((owner) => (
                                            <SelectItem
                                                key={owner.id}
                                                value={String(owner.id)}
                                            >
                                                {owner.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError message={errors.user_id} />
                            </div>
                        ) : (
                            <input
                                type="hidden"
                                name="user_id"
                                value={ownerId}
                            />
                        )}
                        <div className="grid gap-2">
                            <Label>{t('Expires after')}</Label>
                            <Select name="expires_in_days" defaultValue="90">
                                <SelectTrigger className="w-full">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {expiryDays.map((days) => (
                                        <SelectItem
                                            key={days}
                                            value={String(days)}
                                        >
                                            {t(':count days', { count: days })}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={errors.expires_in_days} />
                        </div>
                    </div>

                    <fieldset className="space-y-2">
                        <legend className="mb-2 text-sm font-medium">
                            {t('Abilities')}
                        </legend>
                        <p className="text-xs text-muted-foreground">
                            {t(
                                "A token can never do more than its owner's role allows.",
                            )}
                        </p>
                        <div className="grid gap-2 sm:grid-cols-2">
                            {abilities.map((ability) => {
                                const isAllowed = allowed.includes(
                                    ability.value,
                                );

                                return (
                                    <label
                                        key={`${ownerId}-${ability.value}`}
                                        className="flex items-start gap-2.5 rounded-lg border p-3 text-sm has-[:disabled]:opacity-50 has-[[data-state=checked]]:border-primary/50 has-[[data-state=checked]]:bg-primary/5"
                                    >
                                        <Checkbox
                                            name="abilities[]"
                                            value={ability.value}
                                            disabled={!isAllowed}
                                            defaultChecked={
                                                isAllowed &&
                                                ability.value.endsWith(':read')
                                            }
                                            className="mt-0.5"
                                        />
                                        <span>
                                            <code className="font-mono text-xs">
                                                {ability.value}
                                            </code>
                                            <span className="block text-xs text-muted-foreground">
                                                {ability.label}
                                            </span>
                                        </span>
                                    </label>
                                );
                            })}
                        </div>
                        <InputError
                            message={
                                errors.abilities ??
                                Object.entries(errors).find(([key]) =>
                                    key.startsWith('abilities.'),
                                )?.[1]
                            }
                        />
                    </fieldset>

                    <Button type="submit" disabled={processing || !ownerId}>
                        <KeyRound /> {t('Create token')}
                    </Button>
                </>
            )}
        </Form>
    );
}

export function ApiTokenList({
    tokens,
    showOwner = true,
}: {
    tokens: ApiToken[];
    showOwner?: boolean;
}) {
    const { t } = useTranslation();

    const revoke = (token: ApiToken) => {
        if (
            window.confirm(
                t(
                    'Revoke ":name"? Integrations using it will stop working immediately.',
                    { name: token.name },
                ),
            )
        ) {
            router.delete(token.destroy_url, { preserveScroll: true });
        }
    };

    if (tokens.length === 0) {
        return (
            <p className="rounded-xl border border-dashed p-8 text-center text-sm text-muted-foreground">
                {t('No tokens yet.')}
            </p>
        );
    }

    return (
        <ul className="divide-y overflow-hidden rounded-xl border bg-card shadow-xs">
            {tokens.map((token) => {
                const created = relativeTime(token.created_at);
                const details = [
                    showOwner ? (token.owner?.name ?? t('Unknown user')) : null,
                    token.created_by && token.created_by.id !== token.owner?.id
                        ? t('created :time by :name', {
                              time: created,
                              name: token.created_by.name,
                          })
                        : t('created :time', { time: created }),
                    t('last used :time', {
                        time: token.last_used_at
                            ? relativeTime(token.last_used_at)
                            : t('never'),
                    }),
                    token.expires_at
                        ? t('expires :date', {
                              date: formatDateTime(token.expires_at),
                          })
                        : null,
                ].filter(Boolean);

                return (
                    <li
                        key={token.id}
                        className="flex flex-wrap items-center gap-4 px-4 py-3"
                    >
                        <div className="min-w-0 flex-1">
                            <p className="font-medium">{token.name}</p>
                            <p className="text-xs text-muted-foreground">
                                {details.join(' · ')}
                            </p>
                            <div className="mt-1.5 flex flex-wrap gap-1">
                                {token.abilities.map((ability) => (
                                    <span
                                        key={ability}
                                        className="rounded bg-muted px-1.5 py-0.5 font-mono text-[11px] text-muted-foreground"
                                    >
                                        {ability}
                                    </span>
                                ))}
                            </div>
                        </div>
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={() => revoke(token)}
                            className="text-destructive hover:text-destructive"
                        >
                            <Trash2 /> {t('Revoke')}
                        </Button>
                    </li>
                );
            })}
        </ul>
    );
}

export function NewApiTokenNotice({ token }: { token: NewApiToken }) {
    const { t } = useTranslation();
    const [, copy] = useClipboard();
    const [copied, setCopied] = useState(false);

    return (
        <div className="space-y-2 rounded-xl border border-emerald-500/40 bg-emerald-500/5 p-4">
            <p className="text-sm font-medium">
                {t(
                    "“:name” was created. Copy the token now — it won't be shown again.",
                    { name: token.name },
                )}
            </p>
            <div className="flex items-center gap-2">
                <code className="min-w-0 flex-1 truncate rounded-md border bg-background px-3 py-2 font-mono text-xs">
                    {token.plainText}
                </code>
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    onClick={() => {
                        void copy(token.plainText).then(() => setCopied(true));
                    }}
                >
                    {copied ? <Check /> : <Copy />}{' '}
                    {copied ? t('Copied') : t('Copy')}
                </Button>
            </div>
        </div>
    );
}

/** Where the API lives and where to read its reference. */
export function ApiReferenceHint() {
    const { t } = useTranslation();

    return (
        <p className="text-sm text-muted-foreground">
            {t('Endpoints live under')}{' '}
            <code className="rounded bg-muted px-1 py-0.5 font-mono text-xs">
                /api/v1
            </code>
            . {t('Browse the reference at')}{' '}
            <a
                href="/docs/api"
                target="_blank"
                rel="noreferrer"
                className="font-medium text-primary hover:underline"
            >
                /docs/api
            </a>
            .
        </p>
    );
}
