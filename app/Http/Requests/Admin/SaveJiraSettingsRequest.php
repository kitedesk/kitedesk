<?php

namespace App\Http\Requests\Admin;

use App\Domain\Jira\Enums\StatusCategory;
use App\Domain\Jira\Support\JiraSettings;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveJiraSettingsRequest extends FormRequest
{
    /**
     * Jira Cloud sites only: KiteDesk never calls other hosts with the token.
     */
    public const string SITE_PATTERN = '/^https:\/\/[a-z0-9][a-z0-9-]*\.atlassian\.net\/?$/i';

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $statusRule = ['nullable', 'integer', Rule::exists('ticket_statuses', 'id')->where('is_active', true)];

        return [
            'site_url' => ['required', 'string', 'max:255', 'regex:'.self::SITE_PATTERN],
            'email' => ['required', 'email', 'max:255'],
            'api_token' => [JiraSettings::current()->apiToken === null ? 'required' : 'nullable', 'string', 'max:500'],
            'expires_on' => ['nullable', 'date'],
            'default_project' => ['nullable', 'string', 'max:50'],
            'default_issue_type' => ['nullable', 'string', 'max:50'],
            'status_mapping' => ['array'],
            ...collect(StatusCategory::cases())->mapWithKeys(fn (StatusCategory $category): array => ["status_mapping.{$category->value}" => $statusRule])->all(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['site_url.regex' => __('Enter your Jira Cloud address, such as https://your-company.atlassian.net.')];
    }

    /**
     * @return array{site_url: string, email: string, api_token: ?string, expires_on: ?string, default_project: ?string, default_issue_type: ?string, status_mapping: array<string, int|null>}
     */
    public function settings(): array
    {
        $text = fn (string $key): ?string => filled($this->input($key)) ? trim($this->string($key)->toString()) : null;

        return [
            'site_url' => (string) $text('site_url'),
            'email' => (string) $text('email'),
            'api_token' => $text('api_token'),
            'expires_on' => $text('expires_on'),
            'default_project' => $text('default_project'),
            'default_issue_type' => $text('default_issue_type'),
            'status_mapping' => collect((array) $this->input('status_mapping', []))->map(fn (mixed $id): ?int => is_numeric($id) ? (int) $id : null)->all(),
        ];
    }
}
