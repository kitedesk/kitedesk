<?php

namespace App\Domain\Jira\Support;

use App\Domain\Jira\Enums\StatusCategory;
use App\Domain\Jira\Exceptions\JiraException;
use App\Domain\Jira\Exceptions\JiraNotConnected;
use App\Domain\Jira\Exceptions\JiraRateLimited;
use App\Domain\Jira\Notifications\JiraNeedsAttention;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;

/**
 * Jira Cloud's REST API (v3), signed in with an account's email and API token.
 *
 * @phpstan-type Issue array{id: string, key: string, summary: string, issue_type: string|null, status_id: string|null, status_name: string|null, status_category: StatusCategory|null, resolution: string|null, assignee_name: string|null, url: string}
 */
class JiraClient
{
    /** The issue fields KiteDesk keeps a copy of. */
    private const array ISSUE_FIELDS = ['summary', 'status', 'resolution', 'assignee', 'issuetype'];

    /** Fields the create dialog fills itself, or Jira fills on its own. */
    private const array HANDLED_FIELDS = ['summary', 'description', 'issuetype', 'project', 'reporter'];

    /**
     * @param  bool  $tracksHealth  False for trying out credentials that aren't saved (the "Test
     *                              connection" button): their failures and successes say nothing
     *                              about the saved connection, so they don't touch `JiraHealth`
     *                              or alert anyone.
     */
    public function __construct(private JiraSettings $settings, private bool $tracksHealth = true) {}

    public static function make(?JiraSettings $settings = null, bool $tracksHealth = true): self
    {
        return new self($settings ?? JiraSettings::current(), $tracksHealth);
    }

    /**
     * The Jira account KiteDesk acts as.
     *
     * @return array{id: string, name: string, email: string|null}
     */
    public function myself(): array
    {
        $account = $this->send(fn (PendingRequest $http) => $http->get('myself'))->json();

        return [
            'id' => (string) ($account['accountId'] ?? ''),
            'name' => (string) ($account['displayName'] ?? ''),
            'email' => $account['emailAddress'] ?? null,
        ];
    }

    /**
     * The id of the Jira account KiteDesk acts as, remembered for a day per site and email.
     */
    public function accountId(): string
    {
        $key = 'jira:account:'.md5($this->settings->siteUrl.'|'.$this->settings->email);

        return Cache::remember($key, now()->addDay(), fn (): string => $this->myself()['id']);
    }

    /**
     * Projects the account can see, by name.
     *
     * @return list<array{key: string, name: string}>
     */
    public function projects(): array
    {
        $projects = $this->send(fn (PendingRequest $http) => $http->get('project/search', ['maxResults' => 100, 'orderBy' => 'name']))->json('values', []);

        return array_values(array_map(fn (array $project): array => ['key' => (string) $project['key'], 'name' => (string) $project['name']], $projects));
    }

    /**
     * Issue types that can be created in the project (no subtasks: they need a parent).
     *
     * @return list<array{id: string, name: string}>
     */
    public function issueTypes(string $project): array
    {
        $response = $this->send(fn (PendingRequest $http) => $http->get('issue/createmeta/'.rawurlencode($project).'/issuetypes', ['maxResults' => 100]));
        $types = $response->json('issueTypes') ?? $response->json('values') ?? [];

        return array_values(array_map(
            fn (array $type): array => ['id' => (string) $type['id'], 'name' => (string) $type['name']],
            array_filter($types, fn (array $type): bool => ! ($type['subtask'] ?? false)),
        ));
    }

    /**
     * Names of the fields this issue type requires that KiteDesk doesn't fill (and that have no default).
     *
     * @return list<string>
     */
    public function missingRequiredFields(string $project, string $issueType): array
    {
        $response = $this->send(fn (PendingRequest $http) => $http->get('issue/createmeta/'.rawurlencode($project).'/issuetypes/'.rawurlencode($issueType), ['maxResults' => 200]));
        $fields = $response->json('fields') ?? $response->json('results') ?? $response->json('values') ?? [];

        return array_values(array_map(
            fn (array $field): string => (string) ($field['name'] ?? $field['fieldId']),
            array_filter($fields, fn (array $field): bool => ($field['required'] ?? false)
                && ! ($field['hasDefaultValue'] ?? false)
                && ! in_array($field['fieldId'] ?? $field['key'] ?? '', self::HANDLED_FIELDS, true)),
        ));
    }

    /**
     * @param  array<string, mixed>  $description  ADF document
     * @return Issue
     */
    public function createIssue(string $project, string $issueType, string $summary, array $description): array
    {
        $created = $this->send(fn (PendingRequest $http) => $http->post('issue', ['fields' => [
            'project' => ['key' => $project],
            'issuetype' => ['id' => $issueType],
            'summary' => $summary,
            'description' => $description,
        ]]))->json();

        return $this->issue((string) $created['key']);
    }

    /**
     * @return Issue
     */
    public function issue(string $keyOrId): array
    {
        $issue = $this->send(fn (PendingRequest $http) => $http->get('issue/'.rawurlencode($keyOrId), ['fields' => implode(',', self::ISSUE_FIELDS)]))->json();

        return $this->normalize($issue);
    }

    /**
     * Issues matching a JQL query, at most `$limit`.
     *
     * @return list<Issue>
     */
    public function search(string $jql, int $limit = 10): array
    {
        $issues = $this->send(fn (PendingRequest $http) => $http->post('search/jql', [
            'jql' => $jql,
            'maxResults' => $limit,
            'fields' => self::ISSUE_FIELDS,
        ]))->json('issues', []);

        return array_values(array_map($this->normalize(...), $issues));
    }

    /**
     * Every workflow status on the site, by name, with the group Jira puts it in.
     *
     * @return list<array{id: string, name: string, category: StatusCategory|null}>
     */
    public function statuses(): array
    {
        $statuses = [];

        foreach ((array) $this->send(fn (PendingRequest $http) => $http->get('status'))->json() as $status) {
            if (is_array($status) && isset($status['id'], $status['name'])) {
                $statuses[(string) $status['id']] = [
                    'id' => (string) $status['id'],
                    'name' => (string) $status['name'],
                    'category' => StatusCategory::tryFrom((string) ($status['statusCategory']['key'] ?? '')),
                ];
            }
        }

        usort($statuses, fn (array $a, array $b): int => strnatcasecmp($a['name'], $b['name']));

        return $statuses;
    }

    /**
     * The moves the issue's workflow allows from its current status.
     *
     * @return list<array{id: string, name: string, to: array{id: string, name: string, category: StatusCategory|null}}>
     */
    public function transitions(string $issueKey): array
    {
        $transitions = $this->send(fn (PendingRequest $http) => $http->get('issue/'.rawurlencode($issueKey).'/transitions'))->json('transitions', []);

        return array_values(array_map(fn (array $transition): array => [
            'id' => (string) $transition['id'],
            'name' => (string) $transition['name'],
            'to' => [
                'id' => (string) ($transition['to']['id'] ?? ''),
                'name' => (string) ($transition['to']['name'] ?? $transition['name']),
                'category' => StatusCategory::tryFrom((string) ($transition['to']['statusCategory']['key'] ?? '')),
            ],
        ], $transitions));
    }

    /**
     * Moves the issue along one of its workflow's transitions.
     */
    public function transition(string $issueKey, string $transitionId): void
    {
        $this->send(fn (PendingRequest $http) => $http->post('issue/'.rawurlencode($issueKey).'/transitions', ['transition' => ['id' => $transitionId]]));
    }

    /**
     * @param  array<string, mixed>  $body  ADF document
     * @return string the comment's id
     */
    public function addComment(string $issueKey, array $body): string
    {
        return (string) $this->send(fn (PendingRequest $http) => $http->post('issue/'.rawurlencode($issueKey).'/comment', ['body' => $body]))->json('id');
    }

    /**
     * Shows the ticket on the issue's "Web links"; posting the same `globalId` again updates it.
     */
    public function upsertRemoteLink(string $issueKey, string $globalId, string $url, string $title, bool $resolved): void
    {
        $this->send(fn (PendingRequest $http) => $http->post('issue/'.rawurlencode($issueKey).'/remotelink', [
            'globalId' => $globalId,
            'application' => ['type' => 'com.kitedesk', 'name' => 'KiteDesk'],
            'relationship' => 'support ticket',
            'object' => [
                'url' => $url,
                'title' => $title,
                'status' => ['resolved' => $resolved],
            ],
        ]));
    }

    public function deleteRemoteLink(string $issueKey, string $globalId): void
    {
        $this->send(fn (PendingRequest $http) => $http->delete('issue/'.rawurlencode($issueKey).'/remotelink?'.http_build_query(['globalId' => $globalId])));
    }

    public function browseUrl(string $issueKey): string
    {
        return $this->settings->siteUrl.'/browse/'.$issueKey;
    }

    /**
     * @param  array<string, mixed>  $issue  an issue from the API or a webhook
     * @return Issue
     */
    public function normalize(array $issue): array
    {
        $fields = is_array($issue['fields'] ?? null) ? $issue['fields'] : [];

        return [
            'id' => (string) $issue['id'],
            'key' => (string) $issue['key'],
            'summary' => (string) ($fields['summary'] ?? ''),
            'issue_type' => $fields['issuetype']['name'] ?? null,
            'status_id' => isset($fields['status']['id']) ? (string) $fields['status']['id'] : null,
            'status_name' => $fields['status']['name'] ?? null,
            'status_category' => StatusCategory::tryFrom((string) ($fields['status']['statusCategory']['key'] ?? '')),
            'resolution' => $fields['resolution']['name'] ?? null,
            'assignee_name' => $fields['assignee']['displayName'] ?? null,
            'url' => $this->browseUrl((string) $issue['key']),
        ];
    }

    /**
     * @param  callable(PendingRequest): Response  $request
     *
     * @throws JiraException
     */
    private function send(callable $request): Response
    {
        if (! $this->settings->isConnected()) {
            throw new JiraNotConnected(__('Jira is not connected yet.'));
        }

        try {
            $response = $request(Http::baseUrl($this->settings->siteUrl.'/rest/api/3')
                ->withBasicAuth((string) $this->settings->email, (string) $this->settings->apiToken)
                ->acceptJson()
                ->asJson()
                ->timeout(15)
                ->withOptions(['allow_redirects' => false]));
        } catch (ConnectionException) {
            throw new JiraException(__('Could not reach Jira.'));
        }

        if ($response->status() === 429) {
            throw new JiraRateLimited(max(1, (int) ($response->header('Retry-After') ?: 30)));
        }

        // 403 only means this account may not do that (e.g. in one project); 401 means the credentials are wrong.
        if ($response->status() === 401) {
            $this->connectionLost(__('Jira refused the email and API token. Check them in the Jira settings.'));
        }

        if ($response->failed()) {
            throw new JiraException($this->errorMessage($response), $response->status());
        }

        if ($this->tracksHealth) {
            JiraHealth::clear();
        }

        return $response;
    }

    /**
     * @throws JiraNotConnected
     */
    private function connectionLost(string $message): never
    {
        if ($this->tracksHealth && JiraHealth::fail($message)) {
            Notification::send(JiraNeedsAttention::recipients(), new JiraNeedsAttention($message));
        }

        throw new JiraNotConnected($message, 401);
    }

    private function errorMessage(Response $response): string
    {
        $messages = [
            ...array_values(array_filter((array) $response->json('errorMessages', []), is_string(...))),
            ...array_values(array_filter((array) $response->json('errors', []), is_string(...))),
        ];

        return $messages !== []
            ? implode(' ', $messages)
            : __('Jira answered with an error (HTTP :status).', ['status' => $response->status()]);
    }
}
