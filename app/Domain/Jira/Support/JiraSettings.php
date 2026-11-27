<?php

namespace App\Domain\Jira\Support;

use App\Domain\Jira\Enums\StatusCategory;
use App\Domain\Support\Models\Setting;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

/**
 * The Jira Cloud connection: the site, the Jira account whose API token KiteDesk uses, the
 * secret Jira signs its webhooks with, defaults for new issues and how issue statuses move
 * tickets. The token and secret are encrypted at rest.
 *
 * Stored field names avoid "key" and "token" unless they hold a secret: the settings audit
 * log hides the values of fields named like that.
 */
final readonly class JiraSettings
{
    public const string SETTING = 'jira';

    /**
     * @param  array<string, int|null>  $statusMapping  Jira status category (value) => ticket status id, or null to leave the ticket alone
     * @param  list<array{status_id: string, status_name: string, ticket_status_id: int|null}>  $statusRules  rules for exact Jira statuses, which win over the category mapping; a null ticket status means "don't change"
     */
    public function __construct(
        public ?string $siteUrl = null,
        public ?string $email = null,
        public ?string $apiToken = null,
        public ?CarbonImmutable $expiresOn = null,
        public ?string $webhookSecret = null,
        public ?string $defaultProject = null,
        public ?string $defaultIssueType = null,
        public array $statusMapping = [],
        public array $statusRules = [],
    ) {}

    public static function current(): self
    {
        $setting = Setting::get(self::SETTING);
        $setting = is_array($setting) ? $setting : [];
        $string = fn (string $key): ?string => is_string($setting[$key] ?? null) && $setting[$key] !== '' ? $setting[$key] : null;
        $mapping = is_array($setting['status_mapping'] ?? null) ? $setting['status_mapping'] : [];

        return new self(
            siteUrl: $string('site_url'),
            email: $string('email'),
            apiToken: self::decrypt($string('api_token')),
            expiresOn: $string('expires_on') !== null ? CarbonImmutable::parse($string('expires_on')) : null,
            webhookSecret: self::decrypt($string('webhook_secret')),
            defaultProject: $string('default_project'),
            defaultIssueType: $string('default_issue_type'),
            statusMapping: self::mapping($mapping),
            statusRules: self::rules(is_array($setting['status_rules'] ?? null) ? $setting['status_rules'] : []),
        );
    }

    /**
     * The settings to store when an admin saves the page. A blank token keeps the stored one,
     * and the webhook secret is created the first time.
     *
     * @param  array{site_url: string, email: string, api_token: ?string, expires_on: ?string, default_project: ?string, default_issue_type: ?string, status_mapping: array<string, int|null>, status_rules: list<array{status_id: string, status_name: string, ticket_status_id: int|null}>}  $input
     */
    public function update(array $input): self
    {
        return new self(
            siteUrl: rtrim($input['site_url'], '/'),
            email: $input['email'],
            apiToken: filled($input['api_token']) ? $input['api_token'] : $this->apiToken,
            expiresOn: $input['expires_on'] !== null ? CarbonImmutable::parse($input['expires_on']) : null,
            webhookSecret: $this->webhookSecret ?? self::newSecret(),
            defaultProject: $input['default_project'],
            defaultIssueType: $input['default_issue_type'],
            statusMapping: self::mapping($input['status_mapping']),
            statusRules: self::rules($input['status_rules']),
        );
    }

    /**
     * The same settings with a new webhook secret; the Jira webhook must be updated to match.
     */
    public function withNewSecret(): self
    {
        return new self($this->siteUrl, $this->email, $this->apiToken, $this->expiresOn, self::newSecret(), $this->defaultProject, $this->defaultIssueType, $this->statusMapping, $this->statusRules);
    }

    public function save(): void
    {
        Setting::put(self::SETTING, [
            'site_url' => $this->siteUrl,
            'email' => $this->email,
            'api_token' => $this->apiToken !== null ? Crypt::encryptString($this->apiToken) : null,
            'expires_on' => $this->expiresOn?->toDateString(),
            'webhook_secret' => $this->webhookSecret !== null ? Crypt::encryptString($this->webhookSecret) : null,
            'default_project' => $this->defaultProject,
            'default_issue_type' => $this->defaultIssueType,
            'status_mapping' => $this->statusMapping,
            'status_rules' => $this->statusRules,
        ]);

        JiraHealth::clear();
    }

    /**
     * Enough to call Jira.
     */
    public function isConnected(): bool
    {
        return $this->siteUrl !== null && $this->email !== null && $this->apiToken !== null;
    }

    /**
     * The ticket status an issue moving into this category moves its tickets to, if any.
     */
    public function statusFor(StatusCategory $category): ?int
    {
        return $this->statusMapping[$category->value] ?? null;
    }

    /**
     * The ticket status an issue moving into this Jira status moves its tickets to: the rule
     * for that exact status when there is one (it may say "don't change"), else the mapping
     * for its category.
     */
    public function ticketStatusFor(?string $jiraStatusId, ?StatusCategory $category): ?int
    {
        foreach ($this->statusRules as $rule) {
            if ($jiraStatusId !== null && $rule['status_id'] === $jiraStatusId) {
                return $rule['ticket_status_id'];
            }
        }

        return $category !== null ? $this->statusFor($category) : null;
    }

    /**
     * @param  array<mixed>  $rules
     * @return list<array{status_id: string, status_name: string, ticket_status_id: int|null}>
     */
    private static function rules(array $rules): array
    {
        $result = [];

        foreach ($rules as $rule) {
            if (! is_array($rule) || ! isset($rule['status_id']) || (string) $rule['status_id'] === '') {
                continue;
            }

            // One rule per Jira status; the last one given wins.
            $result[(string) $rule['status_id']] = [
                'status_id' => (string) $rule['status_id'],
                'status_name' => (string) ($rule['status_name'] ?? ''),
                'ticket_status_id' => is_numeric($rule['ticket_status_id'] ?? null) ? (int) $rule['ticket_status_id'] : null,
            ];
        }

        return array_values($result);
    }

    /**
     * @param  array<mixed>  $mapping
     * @return array<string, int|null>
     */
    private static function mapping(array $mapping): array
    {
        $result = [];

        foreach (StatusCategory::cases() as $category) {
            $value = $mapping[$category->value] ?? null;
            $result[$category->value] = is_numeric($value) ? (int) $value : null;
        }

        return $result;
    }

    private static function newSecret(): string
    {
        return Str::random(40);
    }

    /**
     * A value that can't be decrypted (APP_KEY was rotated) counts as missing, so the admin is
     * asked for it again instead of every request failing.
     */
    private static function decrypt(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        try {
            return Crypt::decryptString($value);
        } catch (DecryptException) {
            return null;
        }
    }
}
