import { Head, useForm } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { AdminPageHeader } from '@/components/admin/page-header';
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
import { useTranslation } from '@/hooks/use-translation';
import { edit, update } from '@/routes/admin/security';

type Policy = {
    min_length: number;
    mixed_case: boolean;
    numbers: boolean;
    symbols: boolean;
    uncompromised: boolean;
    expires_after_days: number | null;
    remember_last: number;
    require_two_factor_for_staff: boolean;
};

const expiryOptions = [30, 60, 90, 180, 365];
const rememberOptions = [3, 5, 10];

export default function SecurityPolicy({
    policy,
    maxRemembered,
    staffWithoutTwoFactor,
}: {
    policy: Policy;
    maxRemembered: number;
    staffWithoutTwoFactor: number;
}) {
    const { t, tChoice } = useTranslation();
    const form = useForm(policy);

    const toggle = (
        field: 'mixed_case' | 'numbers' | 'symbols' | 'uncompromised',
        label: string,
        description?: string,
    ) => (
        <CheckboxField
            checked={form.data[field]}
            onChange={(checked) => form.setData(field, checked)}
            label={label}
            description={description}
        />
    );

    return (
        <>
            <Head title={t('Security')} />
            <AdminPageHeader
                title={t('Security')}
                description={t(
                    'The rules passwords must follow, and how people sign in. They apply to the team and to customers.',
                )}
            />

            <form
                onSubmit={(event) => {
                    event.preventDefault();
                    form.put(update.url(), { preserveScroll: true });
                }}
                className="max-w-2xl space-y-10"
            >
                <Section
                    title={t('Password strength')}
                    description={t(
                        'Checked when someone chooses a new password. Current passwords keep working.',
                    )}
                >
                    <div className="grid gap-2">
                        <Label htmlFor="min-length">
                            {t('Minimum length')}
                        </Label>
                        <Input
                            id="min-length"
                            type="number"
                            min={8}
                            max={128}
                            className="w-32"
                            value={form.data.min_length}
                            onChange={(event) =>
                                form.setData(
                                    'min_length',
                                    Number(event.target.value),
                                )
                            }
                        />
                        <InputError message={form.errors.min_length} />
                    </div>
                    {toggle(
                        'mixed_case',
                        t('Require upper and lower case letters'),
                    )}
                    {toggle('numbers', t('Require a number'))}
                    {toggle('symbols', t('Require a symbol'))}
                    {toggle(
                        'uncompromised',
                        t('Refuse passwords found in data breaches'),
                        t(
                            'Checked against Have I Been Pwned. Only the first characters of a hash of the password are sent, never the password.',
                        ),
                    )}
                </Section>

                <Section
                    title={t('Expiry and reuse')}
                    description={t(
                        'When a password expires, its owner must choose a new one the next time they sign in.',
                    )}
                >
                    <div className="grid gap-2">
                        <Label htmlFor="expires-after">
                            {t('Passwords expire')}
                        </Label>
                        <Select
                            value={String(
                                form.data.expires_after_days ?? 'never',
                            )}
                            onValueChange={(value) =>
                                form.setData(
                                    'expires_after_days',
                                    value === 'never' ? null : Number(value),
                                )
                            }
                        >
                            <SelectTrigger
                                id="expires-after"
                                className="w-full sm:w-72"
                            >
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="never">
                                    {t('Never')}
                                </SelectItem>
                                {expiryOptions.map((days) => (
                                    <SelectItem key={days} value={String(days)}>
                                        {t('After :days days', { days })}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={form.errors.expires_after_days} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="remember-last">
                            {t('Reusing passwords')}
                        </Label>
                        <Select
                            value={String(form.data.remember_last)}
                            onValueChange={(value) =>
                                form.setData('remember_last', Number(value))
                            }
                        >
                            <SelectTrigger
                                id="remember-last"
                                className="w-full sm:w-72"
                            >
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="0">
                                    {t('Allowed')}
                                </SelectItem>
                                {[...rememberOptions, maxRemembered].map(
                                    (count) => (
                                        <SelectItem
                                            key={count}
                                            value={String(count)}
                                        >
                                            {t(
                                                'Not the last :count passwords',
                                                { count },
                                            )}
                                        </SelectItem>
                                    ),
                                )}
                            </SelectContent>
                        </Select>
                        <InputError message={form.errors.remember_last} />
                    </div>
                </Section>

                <Section
                    title={t('Two-factor authentication')}
                    description={t(
                        'A code from an authenticator app or a passkey, on top of the password.',
                    )}
                >
                    <CheckboxField
                        checked={form.data.require_two_factor_for_staff}
                        onChange={(checked) =>
                            form.setData(
                                'require_two_factor_for_staff',
                                checked,
                            )
                        }
                        label={t('Require it for the team')}
                        description={
                            staffWithoutTwoFactor > 0
                                ? tChoice(
                                      ':count team member doesn’t use it yet and will be asked to turn it on before continuing.|:count team members don’t use it yet and will be asked to turn it on before continuing.',
                                      staffWithoutTwoFactor,
                                  )
                                : t('Everyone on the team already uses it.')
                        }
                    />
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
        <section className="space-y-5">
            <div className="space-y-1">
                <h2 className="text-base font-semibold">{title}</h2>
                <p className="text-sm text-muted-foreground">{description}</p>
            </div>
            {children}
        </section>
    );
}

function CheckboxField({
    checked,
    onChange,
    label,
    description,
}: {
    checked: boolean;
    onChange: (checked: boolean) => void;
    label: string;
    description?: string;
}) {
    return (
        <Label className="flex items-start gap-3 font-normal">
            <Checkbox
                checked={checked}
                onCheckedChange={(value) => onChange(value === true)}
                className="mt-0.5"
            />
            <span className="space-y-1">
                <span className="block font-medium">{label}</span>
                {description && (
                    <span className="block text-sm text-muted-foreground">
                        {description}
                    </span>
                )}
            </span>
        </Label>
    );
}

SecurityPolicy.layout = {
    breadcrumbs: [
        { title: 'Admin center', href: '/admin' },
        { title: 'Security', href: edit() },
    ],
};
