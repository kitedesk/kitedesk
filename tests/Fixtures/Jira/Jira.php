<?php

namespace Tests\Fixtures\Jira;

use App\Domain\Jira\Support\JiraModule;
use App\Domain\Jira\Support\JiraSettings;
use App\Domain\Support\Extensions\ModuleRegistry;
use Illuminate\Support\Facades\Http;

/**
 * Turns the Jira module on and connects it to a pretend site, and builds what Jira sends.
 */
final class Jira
{
    public const string SITE = 'https://acme.atlassian.net';

    public const string API = self::SITE.'/rest/api/3';

    public const string SECRET = 'webhook-secret';

    /**
     * @param  array<string, int|null>  $statusMapping
     */
    public static function connect(array $statusMapping = []): void
    {
        self::blockStrayRequests();

        $modules = app(ModuleRegistry::class);
        $modules->install(JiraModule::ID);
        $modules->enable(JiraModule::ID);

        (new JiraSettings(
            siteUrl: self::SITE,
            email: 'bot@acme.test',
            apiToken: 'jira-api-token',
            webhookSecret: self::SECRET,
            defaultProject: 'ENG',
            defaultIssueType: '10001',
            statusMapping: $statusMapping,
        ))->save();
    }

    /**
     * Nothing may reach a real Jira: each test fakes the calls it expects. Inertia's
     * server-side renderer is still allowed (pages fall back without it).
     */
    public static function blockStrayRequests(): void
    {
        Http::preventStrayRequests();
        Http::allowStrayRequests(['http://127.0.0.1:13714/*']);
    }

    /**
     * An issue as the REST API and webhooks describe it.
     *
     * @return array<string, mixed>
     */
    public static function issue(string $key = 'ENG-12', string $id = '10012', string $status = 'To Do', string $category = 'new', ?string $resolution = null, ?string $statusId = null): array
    {
        return [
            'id' => $id,
            'key' => $key,
            'fields' => [
                'summary' => 'Export fails for large files',
                'issuetype' => ['name' => 'Bug'],
                'status' => ['id' => $statusId ?? (string) (1000 + crc32($status) % 1000), 'name' => $status, 'statusCategory' => ['key' => $category]],
                'resolution' => $resolution !== null ? ['name' => $resolution] : null,
                'assignee' => ['displayName' => 'Dana Dev'],
            ],
        ];
    }

    /**
     * POSTs a webhook delivery signed with the secret, as Jira does.
     *
     * @param  array<string, mixed>  $payload
     * @return array{0: string, 1: array<string, string>}
     */
    public static function delivery(array $payload, ?string $identifier = null, string $secret = self::SECRET): array
    {
        $body = (string) json_encode($payload);

        return [$body, [
            'Content-Type' => 'application/json',
            'X-Hub-Signature' => 'sha256='.hash_hmac('sha256', $body, $secret),
            'X-Atlassian-Webhook-Identifier' => $identifier ?? bin2hex(random_bytes(8)),
        ]];
    }
}
