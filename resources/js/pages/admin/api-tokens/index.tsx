import { Head, usePage } from '@inertiajs/react';
import ApiTokenController from '@/actions/App/Http/Controllers/Admin/ApiTokenController';
import {
    ApiReferenceHint,
    ApiTokenList,
    CreateApiTokenForm,
    NewApiTokenNotice,
} from '@/components/api-tokens';
import type {
    ApiAbilityOption,
    ApiToken,
    NewApiToken,
    TokenOwner,
} from '@/components/api-tokens';
import Heading from '@/components/heading';
import { useTranslation } from '@/hooks/use-translation';
import { index } from '@/routes/admin/api-tokens';

type Props = {
    tokens: ApiToken[];
    owners: TokenOwner[];
    abilities: ApiAbilityOption[];
    expiryDays: number[];
};

export default function ApiTokens({
    tokens,
    owners,
    abilities,
    expiryDays,
}: Props) {
    const { t } = useTranslation();
    const newToken = (usePage().flash as { newToken?: NewApiToken }).newToken;

    return (
        <>
            <Head title={t('API tokens')} />

            <div className="max-w-4xl space-y-10">
                <div className="space-y-3">
                    <Heading
                        title={t('API tokens')}
                        description={t(
                            "Every token in the help desk. Integration tokens act as a team member, within what that person's role allows.",
                        )}
                    />
                    <ApiReferenceHint />
                </div>

                {newToken && <NewApiTokenNotice token={newToken} />}

                <section className="space-y-4 rounded-xl border bg-card p-5 shadow-xs">
                    <div className="space-y-1">
                        <h2 className="font-medium">
                            {t('Create an integration token')}
                        </h2>
                        <p className="text-sm text-muted-foreground">
                            {t(
                                'You can create tokens for people who cannot do more than you. Everyone can also create their own in Settings.',
                            )}
                        </p>
                    </div>
                    <CreateApiTokenForm
                        form={ApiTokenController.store.form()}
                        owners={owners}
                        abilities={abilities}
                        expiryDays={expiryDays}
                    />
                </section>

                <section className="space-y-3">
                    <h2 className="font-medium">{t('Active tokens')}</h2>
                    <ApiTokenList tokens={tokens} />
                </section>
            </div>
        </>
    );
}

ApiTokens.layout = {
    breadcrumbs: [
        { title: 'Admin center', href: '/admin' },
        { title: 'API tokens', href: index() },
    ],
};
