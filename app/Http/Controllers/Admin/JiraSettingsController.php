<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Jira\Enums\StatusCategory;
use App\Domain\Jira\Exceptions\JiraException;
use App\Domain\Jira\Support\JiraClient;
use App\Domain\Jira\Support\JiraHealth;
use App\Domain\Jira\Support\JiraSettings;
use App\Domain\Support\EnumOptions;
use App\Domain\Tickets\Support\CustomStatuses;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveJiraSettingsRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The Jira Cloud connection, defaults for new issues, how issue statuses move tickets, and
 * what to set up on Jira's side for its webhook.
 */
class JiraSettingsController extends Controller
{
    public function edit(): Response
    {
        $settings = JiraSettings::current();

        return Inertia::render('admin/jira/edit', [
            'settings' => [
                'site_url' => $settings->siteUrl ?? '',
                'email' => $settings->email ?? '',
                'api_token' => '',
                'expires_on' => $settings->expiresOn?->toDateString() ?? '',
                'default_project' => $settings->defaultProject ?? '',
                'default_issue_type' => $settings->defaultIssueType ?? '',
                'status_mapping' => collect($settings->statusMapping)->map(fn (?int $id): string => $id !== null ? (string) $id : '')->all(),
            ],
            'hasToken' => $settings->apiToken !== null,
            'connected' => $settings->isConnected(),
            'error' => JiraHealth::error(),
            'categories' => EnumOptions::for(StatusCategory::class),
            'statuses' => CustomStatuses::options(),
            'projects' => Inertia::defer(fn (): array => $this->projects($settings)),
            'webhook' => [
                'url' => route('jira.webhook'),
                'secret' => $settings->webhookSecret,
            ],
        ]);
    }

    public function update(SaveJiraSettingsRequest $request): RedirectResponse
    {
        JiraSettings::current()->update($request->settings())->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Jira settings saved.')]);

        return back();
    }

    /**
     * Signs in with the address, email and token on the form (a blank token uses the saved one).
     */
    public function test(Request $request): RedirectResponse
    {
        $input = $request->validate([
            'site_url' => ['required', 'string', 'max:255', 'regex:'.SaveJiraSettingsRequest::SITE_PATTERN],
            'email' => ['required', 'email', 'max:255'],
            'api_token' => ['nullable', 'string', 'max:500'],
        ], (new SaveJiraSettingsRequest)->messages());

        $saved = JiraSettings::current();
        $settings = new JiraSettings(
            siteUrl: rtrim($input['site_url'], '/'),
            email: $input['email'],
            apiToken: filled($input['api_token'] ?? null) ? $input['api_token'] : $saved->apiToken,
        );

        try {
            $account = JiraClient::make($settings)->myself();
        } catch (JiraException $exception) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('The connection failed: :error', ['error' => $exception->getMessage()])]);

            return back();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Connected to Jira as :name.', ['name' => $account['name']])]);

        return back();
    }

    /**
     * A new webhook secret. The webhook in Jira stops being accepted until it gets the new one.
     */
    public function rotateSecret(): RedirectResponse
    {
        JiraSettings::current()->withNewSecret()->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('New webhook secret created. Update it in Jira.')]);

        return back();
    }

    /**
     * Issue types of a project, for the default issue type picker.
     */
    public function issueTypes(Request $request): JsonResponse
    {
        $project = $request->validate(['project' => ['required', 'string', 'max:50']])['project'];

        try {
            return response()->json(['issue_types' => JiraClient::make()->issueTypes($project)]);
        } catch (JiraException $exception) {
            throw ValidationException::withMessages(['jira' => $exception->getMessage()]);
        }
    }

    /**
     * @return list<array{key: string, name: string}>
     */
    private function projects(JiraSettings $settings): array
    {
        if (! $settings->isConnected()) {
            return [];
        }

        try {
            return JiraClient::make($settings)->projects();
        } catch (JiraException) {
            return [];
        }
    }
}
