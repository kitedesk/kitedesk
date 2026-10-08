import { Head, usePage } from '@inertiajs/react';
import ApiTokenController from '@/actions/App/Http/Controllers/Settings/ApiTokenController';
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

type Props = {
    tokens: ApiToken[];
    owner: TokenOwner;
    abilities: ApiAbilityOption[];
    expiryDays: number[];
};

export default function ApiTokens({
    tokens,
    owner,
    abilities,
    expiryDays,
}: Props) {
    const { t } = useTranslation();
    const newToken = (usePage().flash as { newToken?: NewApiToken }).newToken;

    return (
        <>
            <Head title={t('API tokens')} />

            <div className="space-y-6">
                <div className="space-y-3">
                    <Heading
                        variant="small"
                        title={t('API tokens')}
                        description={t(
                            'Tokens let your scripts and tools use the REST API as you. They can do what your role allows. Send them as a Bearer token.',
                        )}
                    />
                    <ApiReferenceHint />
                </div>

                {newToken && <NewApiTokenNotice token={newToken} />}

                <section className="space-y-4 rounded-xl border p-5">
                    <h2 className="font-medium">{t('Create a token')}</h2>
                    <CreateApiTokenForm
                        form={ApiTokenController.store.form()}
                        owners={[owner]}
                        abilities={abilities}
                        expiryDays={expiryDays}
                    />
                </section>

                <section className="space-y-3">
                    <h2 className="font-medium">{t('Your tokens')}</h2>
                    <ApiTokenList tokens={tokens} showOwner={false} />
                </section>
            </div>
        </>
    );
}
