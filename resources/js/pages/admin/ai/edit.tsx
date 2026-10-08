import { Head, useForm } from '@inertiajs/react';
import { Copy, PlugZap, ShieldCheck } from 'lucide-react';
import { toast } from 'sonner';
import { AdminPageHeader } from '@/components/admin/page-header';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useClipboard } from '@/hooks/use-clipboard';
import { useTranslation } from '@/hooks/use-translation';
import { relativeTime } from '@/lib/tickets';
import { edit, test, update } from '@/routes/admin/ai';

type Settings = {
    enabled: boolean;
    base_url: string;
    api_key: string;
    model: string;
    summaries: boolean;
    drafts: boolean;
    improve: boolean;
    mcp_enabled: boolean;
    instructions: string;
    context_messages: number;
};

type ConnectedApp = { user: string; app: string; connected_at: string | null };

function Toggle({
    checked,
    onChange,
    title,
    description,
    disabled = false,
}: {
    checked: boolean;
    onChange: (checked: boolean) => void;
    title: string;
    description: string;
    disabled?: boolean;
}) {
    return (
        <Label className="flex items-start gap-3 font-normal">
            <Checkbox
                checked={checked}
                disabled={disabled}
                onCheckedChange={(value) => onChange(value === true)}
                className="mt-0.5"
            />
            <span className="space-y-1">
                <span className="block font-medium">{title}</span>
                <span className="block text-sm text-muted-foreground">
                    {description}
                </span>
            </span>
        </Label>
    );
}

export default function AiSettings({
    settings,
    hasApiKey,
    mcpUrl,
    connectedApps,
}: {
    settings: Settings;
    hasApiKey: boolean;
    mcpUrl: string;
    connectedApps: ConnectedApp[];
}) {
    const { t } = useTranslation();
    const form = useForm(settings);
    const testing = useForm({ base_url: '', api_key: '', model: '' });
    const [, copy] = useClipboard();

    const testConnection = () => {
        testing.transform(() => ({
            base_url: form.data.base_url,
            api_key: form.data.api_key,
            model: form.data.model,
        }));
        testing.post(test.url(), { preserveScroll: true, preserveState: true });
    };

    const clientConfig = JSON.stringify(
        {
            mcpServers: {
                kitedesk: {
                    type: 'http',
                    url: mcpUrl,
                    headers: { Authorization: 'Bearer <API token>' },
                },
            },
        },
        null,
        2,
    );

    return (
        <>
            <Head title={t('AI assistant')} />
            <AdminPageHeader
                title={t('AI assistant')}
                description={t(
                    'Connect any OpenAI-compatible service (OpenAI, OpenRouter, Ollama, LM Studio, vLLM…) so agents can summarize tickets and draft or improve replies. Agents always review drafts before sending.',
                )}
            />

            <form
                onSubmit={(event) => {
                    event.preventDefault();
                    form.put(update.url(), {
                        preserveScroll: true,
                        onSuccess: () => form.setData('api_key', ''),
                    });
                }}
                className="grid max-w-4xl gap-8 lg:grid-cols-[minmax(0,1fr)_18rem]"
            >
                <div className="space-y-8">
                    <section className="space-y-5">
                        <Toggle
                            checked={form.data.enabled}
                            onChange={(checked) =>
                                form.setData('enabled', checked)
                            }
                            title={t('Turn on the AI assistant')}
                            description={t(
                                'Agents need the "Use the AI assistant" permission in their role.',
                            )}
                        />

                        <div className="grid gap-2">
                            <Label htmlFor="ai-base-url">
                                {t('API base URL')}
                            </Label>
                            <Input
                                id="ai-base-url"
                                value={form.data.base_url}
                                onChange={(event) =>
                                    form.setData('base_url', event.target.value)
                                }
                                placeholder="https://api.openai.com/v1"
                            />
                            <p className="text-xs text-muted-foreground">
                                {t(
                                    'The address ending before /chat/completions. For Ollama: http://localhost:11434/v1',
                                )}
                            </p>
                            <InputError message={form.errors.base_url} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="ai-api-key">{t('API key')}</Label>
                            <Input
                                id="ai-api-key"
                                type="password"
                                autoComplete="off"
                                value={form.data.api_key}
                                onChange={(event) =>
                                    form.setData('api_key', event.target.value)
                                }
                                placeholder={
                                    hasApiKey
                                        ? t('Saved. Leave blank to keep it.')
                                        : t('Optional for local models')
                                }
                            />
                            <p className="text-xs text-muted-foreground">
                                {t(
                                    'Stored encrypted. It is never shown again.',
                                )}
                            </p>
                            <InputError message={form.errors.api_key} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="ai-model">{t('Model')}</Label>
                            <Input
                                id="ai-model"
                                value={form.data.model}
                                onChange={(event) =>
                                    form.setData('model', event.target.value)
                                }
                                placeholder="gpt-4.1-mini"
                            />
                            <InputError message={form.errors.model} />
                        </div>

                        <div>
                            <Button
                                type="button"
                                variant="outline"
                                disabled={
                                    testing.processing ||
                                    form.data.base_url === '' ||
                                    form.data.model === ''
                                }
                                onClick={testConnection}
                            >
                                {testing.processing ? <Spinner /> : <PlugZap />}
                                {t('Test connection')}
                            </Button>
                            <InputError
                                message={
                                    testing.errors.base_url ??
                                    testing.errors.model
                                }
                            />
                        </div>
                    </section>

                    <section className="space-y-5 border-t pt-6">
                        <h2 className="font-medium">{t('Features')}</h2>
                        <Toggle
                            checked={form.data.summaries}
                            onChange={(checked) =>
                                form.setData('summaries', checked)
                            }
                            title={t('Ticket summaries')}
                            description={t(
                                'A short briefing on the ticket, for agents picking it up.',
                            )}
                        />
                        <Toggle
                            checked={form.data.drafts}
                            onChange={(checked) =>
                                form.setData('drafts', checked)
                            }
                            title={t('Draft replies')}
                            description={t(
                                'Drafts based on the conversation and related help center articles.',
                            )}
                        />
                        <Toggle
                            checked={form.data.improve}
                            onChange={(checked) =>
                                form.setData('improve', checked)
                            }
                            title={t('Improve writing')}
                            description={t(
                                'Fix, shorten or change the tone of the reply an agent is writing.',
                            )}
                        />

                        <div className="grid gap-2">
                            <Label htmlFor="ai-instructions">
                                {t('House style (optional)')}
                            </Label>
                            <textarea
                                id="ai-instructions"
                                rows={4}
                                maxLength={2000}
                                value={form.data.instructions}
                                onChange={(event) =>
                                    form.setData(
                                        'instructions',
                                        event.target.value,
                                    )
                                }
                                placeholder={t(
                                    'e.g. Address customers by first name. Never promise delivery dates. Our refund window is 30 days.',
                                )}
                                className="rounded-md border bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30"
                            />
                            <p className="text-xs text-muted-foreground">
                                {t(
                                    'Added to every request the assistant makes.',
                                )}
                            </p>
                            <InputError message={form.errors.instructions} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="ai-context">
                                {t('Messages to read')}
                            </Label>
                            <Input
                                id="ai-context"
                                type="number"
                                min={5}
                                max={100}
                                className="w-28"
                                value={form.data.context_messages}
                                onChange={(event) =>
                                    form.setData(
                                        'context_messages',
                                        Number(event.target.value),
                                    )
                                }
                            />
                            <p className="text-xs text-muted-foreground">
                                {t(
                                    'How many of the latest messages the assistant reads. More gives better context but costs more.',
                                )}
                            </p>
                            <InputError
                                message={form.errors.context_messages}
                            />
                        </div>
                    </section>

                    <section className="space-y-5 border-t pt-6">
                        <h2 className="font-medium">{t('MCP server')}</h2>
                        <Toggle
                            checked={form.data.mcp_enabled}
                            onChange={(checked) =>
                                form.setData('mcp_enabled', checked)
                            }
                            title={t('Let AI apps connect to KiteDesk')}
                            description={t(
                                'Claude, ChatGPT, Cursor and other MCP clients can search and read tickets and articles, reply, add notes and update tickets, as the signed-in agent and within their role. This does not need the AI assistant above.',
                            )}
                        />

                        {form.data.mcp_enabled && (
                            <div className="space-y-3 rounded-lg border bg-muted/30 p-4 text-sm">
                                <div className="flex items-center gap-2">
                                    <code className="min-w-0 flex-1 truncate rounded bg-background px-2 py-1 text-xs">
                                        {mcpUrl}
                                    </code>
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="outline"
                                        onClick={async () => {
                                            await copy(mcpUrl);
                                            toast.success(t('Copied.'));
                                        }}
                                    >
                                        <Copy /> {t('Copy')}
                                    </Button>
                                </div>
                                <p className="text-muted-foreground">
                                    {t(
                                        'Apps that support OAuth only need this URL: agents sign in to KiteDesk and approve the app. For other clients, create an API token under API tokens and use this configuration:',
                                    )}
                                </p>
                                <pre className="overflow-x-auto rounded bg-background p-3 text-xs">
                                    {clientConfig}
                                </pre>
                            </div>
                        )}
                    </section>

                    <div className="flex justify-end">
                        <Button type="submit" disabled={form.processing}>
                            {t('Save')}
                        </Button>
                    </div>
                </div>

                <aside className="space-y-4 self-start">
                    <div className="space-y-2 rounded-xl border bg-card p-4 text-sm shadow-xs">
                        <p className="flex items-center gap-1.5 text-xs font-medium tracking-wide text-muted-foreground uppercase">
                            <ShieldCheck className="size-3.5" /> {t('Privacy')}
                        </p>
                        <p>
                            {t(
                                'When an agent uses the assistant, the ticket conversation (including internal notes) and any chosen articles are sent to this service.',
                            )}
                        </p>
                        <p className="text-muted-foreground">
                            {t(
                                'Secrets and attachment files are never sent. Check the provider’s data retention terms, or use a model you host yourself.',
                            )}
                        </p>
                    </div>

                    <div className="space-y-2 rounded-xl border bg-card p-4 text-sm shadow-xs">
                        <p className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                            {t('Connected apps')}
                        </p>
                        {connectedApps.length === 0 ? (
                            <p className="text-muted-foreground">
                                {t('No agent has connected an app yet.')}
                            </p>
                        ) : (
                            <ul className="space-y-2">
                                {connectedApps.map((app, index) => (
                                    <li key={index}>
                                        <span className="font-medium">
                                            {app.app}
                                        </span>{' '}
                                        <span className="text-muted-foreground">
                                            {t('by :name', { name: app.user })}
                                            {app.connected_at &&
                                                ` · ${relativeTime(app.connected_at)}`}
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </div>
                </aside>
            </form>
        </>
    );
}

AiSettings.layout = {
    breadcrumbs: [
        { title: 'Admin center', href: '/admin' },
        { title: 'AI assistant', href: edit() },
    ],
};
