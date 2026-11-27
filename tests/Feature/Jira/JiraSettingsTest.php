<?php

use App\Domain\Jira\Enums\StatusCategory;
use App\Domain\Jira\Support\JiraHealth;
use App\Domain\Jira\Support\JiraSettings;
use App\Domain\Support\Extensions\ModuleRegistry;
use App\Domain\Support\Models\Setting;
use App\Domain\Tickets\Enums\TicketStatus;
use App\Domain\Tickets\Support\CustomStatuses;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Fixtures\Jira\Jira;

beforeEach(function () {
    Jira::blockStrayRequests();

    $modules = app(ModuleRegistry::class);
    $modules->install('jira');
    $modules->enable('jira');

    $this->admin = User::factory()->admin()->create();
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function jiraForm(array $overrides = []): array
{
    return [
        'site_url' => 'https://acme.atlassian.net/',
        'email' => 'bot@acme.test',
        'api_token' => 'secret-token',
        'expires_on' => '2027-06-30',
        'default_project' => 'ENG',
        'default_issue_type' => '10001',
        'status_mapping' => ['new' => null, 'indeterminate' => null, 'done' => CustomStatuses::defaultFor(TicketStatus::Solved)->id],
        ...$overrides,
    ];
}

test('admins connect Jira; the token is stored encrypted and never sent back to the page', function () {
    $this->actingAs($this->admin)->put(route('admin.jira.update'), jiraForm())->assertSessionHasNoErrors();

    $settings = JiraSettings::current();
    expect($settings->siteUrl)->toBe('https://acme.atlassian.net')
        ->and($settings->apiToken)->toBe('secret-token')
        ->and($settings->webhookSecret)->toHaveLength(40)
        ->and($settings->statusFor(StatusCategory::Done))->toBe(CustomStatuses::defaultFor(TicketStatus::Solved)->id)
        ->and(json_encode(Setting::query()->find('jira')->value))->not->toContain('secret-token');

    Http::fake(['acme.atlassian.net/*' => Http::response(['values' => [['key' => 'ENG', 'name' => 'Engineering']]])]);

    $this->actingAs($this->admin)
        ->get(route('admin.jira.edit'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/jira/edit')
            ->where('settings.api_token', '')
            ->where('hasToken', true)
            ->where('webhook.url', route('jira.webhook'))
            ->loadDeferredProps(fn (Assert $reload) => $reload->where('projects.0.key', 'ENG')));

    // A blank token keeps the saved one.
    $this->actingAs($this->admin)->put(route('admin.jira.update'), jiraForm(['api_token' => '']))->assertSessionHasNoErrors();
    expect(JiraSettings::current()->apiToken)->toBe('secret-token');
});

test('only Jira Cloud addresses are accepted', function (string $site) {
    $this->actingAs($this->admin)
        ->put(route('admin.jira.update'), jiraForm(['site_url' => $site]))
        ->assertSessionHasErrors('site_url');
})->with([
    'plain http' => 'http://acme.atlassian.net',
    'another host' => 'https://jira.acme.test',
    'lookalike' => 'https://acme.atlassian.net.evil.test',
]);

test('testing the connection signs in with the form\'s values', function () {
    Http::fake([
        'acme.atlassian.net/rest/api/3/myself' => Http::sequence()
            ->push(['displayName' => 'KiteDesk Bot'])
            ->push(null, 401),
    ]);

    $this->actingAs($this->admin)
        ->post(route('admin.jira.test'), ['site_url' => 'https://acme.atlassian.net', 'email' => 'bot@acme.test', 'api_token' => 't'])
        ->assertInertiaFlash('toast.type', 'success');

    $this->actingAs($this->admin)
        ->post(route('admin.jira.test'), ['site_url' => 'https://acme.atlassian.net', 'email' => 'bot@acme.test', 'api_token' => 'wrong'])
        ->assertInertiaFlash('toast.type', 'error');
});

test('a new webhook secret replaces the old one', function () {
    $this->actingAs($this->admin)->put(route('admin.jira.update'), jiraForm());
    $old = JiraSettings::current()->webhookSecret;

    $this->actingAs($this->admin)->post(route('admin.jira.secret'))->assertRedirect();

    expect(JiraSettings::current()->webhookSecret)->not->toBe($old)
        ->and(JiraSettings::current()->apiToken)->toBe('secret-token');
});

test('the settings need the integrations permission', function () {
    $this->actingAs(User::factory()->agent()->create())
        ->get(route('admin.jira.edit'))
        ->assertForbidden();
});

test('testing other credentials leaves the saved connection\'s state alone', function () {
    Notification::fake();
    $this->actingAs($this->admin)->put(route('admin.jira.update'), jiraForm());
    Http::fake(['acme.atlassian.net/rest/api/3/myself' => Http::sequence()
        ->push(null, 401)
        ->push(['accountId' => 'x', 'displayName' => 'Other'])]);

    $this->actingAs($this->admin)
        ->post(route('admin.jira.test'), ['site_url' => 'https://acme.atlassian.net', 'email' => 'bot@acme.test', 'api_token' => 'typo'])
        ->assertInertiaFlash('toast.type', 'error');

    expect(JiraHealth::error())->toBeNull();
    Notification::assertNothingSent();

    // A real outage stays reported even when a test with other credentials works.
    JiraHealth::fail('Jira refused the email and API token.');

    $this->actingAs($this->admin)
        ->post(route('admin.jira.test'), ['site_url' => 'https://acme.atlassian.net', 'email' => 'other@acme.test', 'api_token' => 'other'])
        ->assertInertiaFlash('toast.type', 'success');

    expect(JiraHealth::error())->not->toBeNull();
});
