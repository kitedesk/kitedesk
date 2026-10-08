import { Head } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { useTranslation } from '@/hooks/use-translation';

type Props = {
    client: { id: string; name: string };
    authToken: string;
    csrfToken: string;
};

/**
 * OAuth consent for an MCP client (Claude, ChatGPT, Cursor…). Plain HTML forms rather than
 * Inertia visits: approving redirects to the client's own URL, which an XHR can't follow.
 */
export default function AuthorizeApp({ client, authToken, csrfToken }: Props) {
    const { t } = useTranslation();
    const hidden = (
        <>
            <input type="hidden" name="_token" value={csrfToken} />
            <input type="hidden" name="state" value="" />
            <input type="hidden" name="client_id" value={client.id} />
            <input type="hidden" name="auth_token" value={authToken} />
        </>
    );

    return (
        <>
            <Head title={t('Connect an app')} />

            <div className="space-y-6">
                <div className="space-y-3 rounded-lg border p-4 text-sm">
                    <p>
                        {t(
                            ':name wants to use KiteDesk as you. It will be able to:',
                            { name: client.name },
                        )}
                    </p>
                    <ul className="list-disc space-y-1 pl-5 text-muted-foreground">
                        <li>{t('Search and read the tickets you can see')}</li>
                        <li>
                            {t(
                                'Reply, add internal notes and update tickets, as your role allows',
                            )}
                        </li>
                        <li>{t('Search and read help center articles')}</li>
                    </ul>
                    <p className="text-muted-foreground">
                        {t(
                            'Only connect apps you trust. You can disconnect it at any time in Settings → Connected apps.',
                        )}
                    </p>
                </div>

                <div className="flex gap-3">
                    <form
                        method="post"
                        action="/oauth/authorize"
                        className="flex-1"
                    >
                        <input type="hidden" name="_method" value="DELETE" />
                        {hidden}
                        <Button
                            type="submit"
                            variant="outline"
                            className="w-full"
                        >
                            {t('Deny')}
                        </Button>
                    </form>
                    <form
                        method="post"
                        action="/oauth/authorize"
                        className="flex-1"
                    >
                        {hidden}
                        <Button type="submit" className="w-full">
                            {t('Approve')}
                        </Button>
                    </form>
                </div>
            </div>
        </>
    );
}

AuthorizeApp.layout = {
    title: 'Connect an app',
    description:
        'An AI assistant is asking to connect to your KiteDesk account.',
};
