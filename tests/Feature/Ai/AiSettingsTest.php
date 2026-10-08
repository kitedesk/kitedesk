<?php

use App\Domain\Ai\Agents\ConnectionCheck;
use App\Domain\Ai\Support\AiSettings;
use App\Domain\Support\Models\Setting;
use App\Models\User;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Laravel\Ai\Prompts\AgentPrompt;
use Laravel\Passport\Client;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Token;

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function aiSettingsForm(array $overrides = []): array
{
    return [
        'enabled' => true,
        'base_url' => 'https://openrouter.ai/api/v1/',
        'api_key' => 'sk-secret',
        'model' => 'openai/gpt-4.1-mini',
        'summaries' => true,
        'drafts' => true,
        'improve' => false,
        'mcp_enabled' => true,
        'instructions' => 'Be brief.',
        'context_messages' => 20,
        ...$overrides,
    ];
}

function mcpClient(): Client
{
    return app(ClientRepository::class)->createAuthorizationCodeGrantClient('Claude', ['https://claude.ai/api/mcp/auth_callback'], confidential: false);
}

test('admins save the connection; the key is stored encrypted and kept when left blank', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->put(route('admin.ai.update'), aiSettingsForm())->assertRedirect();
    $this->actingAs($admin)->put(route('admin.ai.update'), aiSettingsForm(['api_key' => '', 'model' => 'other-model']))->assertRedirect();

    $settings = AiSettings::current();
    expect($settings->apiKey)->toBe('sk-secret')
        ->and($settings->baseUrl)->toBe('https://openrouter.ai/api/v1')
        ->and($settings->model)->toBe('other-model')
        ->and($settings->features())->toBe(['summaries' => true, 'drafts' => true, 'improve' => false])
        ->and(json_encode(Setting::get(AiSettings::SETTING)))->not->toContain('sk-secret');

    $this->actingAs($admin)->get(route('admin.ai.edit'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('settings.api_key', '')
            ->where('hasApiKey', true)
            ->where('mcpUrl', route('mcp')));
});

test('only admins with the integrations permission see the AI settings', function () {
    $this->actingAs(User::factory()->agent()->create())->get(route('admin.ai.edit'))->assertForbidden();
});

test('test connection prompts the model with the unsaved form values', function () {
    ConnectionCheck::fake(['OK']);
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->post(route('admin.ai.test'), ['base_url' => 'http://localhost:11434/v1', 'api_key' => '', 'model' => 'llama3.2'])
        ->assertRedirect()
        ->assertInertiaFlash('toast.type', 'success');

    ConnectionCheck::assertPrompted(fn (AgentPrompt $prompt): bool => $prompt->agent instanceof ConnectionCheck
        && $prompt->agent->settings->baseUrl === 'http://localhost:11434/v1'
        && $prompt->agent->settings->model === 'llama3.2');
});

test('only staff can approve an AI app', function () {
    $client = mcpClient();
    $verifier = Str::random(64);
    $authorize = '/oauth/authorize?'.http_build_query([
        'client_id' => $client->getKey(),
        'redirect_uri' => 'https://claude.ai/api/mcp/auth_callback',
        'response_type' => 'code',
        'scope' => 'mcp:use',
        'state' => 'xyz',
        'code_challenge' => rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '='),
        'code_challenge_method' => 'S256',
    ]);

    $this->actingAs(User::factory()->create())->get($authorize)->assertForbidden();
    $this->actingAs(User::factory()->agent()->create())->get($authorize)
        ->assertInertia(fn (AssertableInertia $page) => $page->component('auth/authorize-app')->where('client.name', 'Claude'));
});

test('staff see and disconnect their connected apps', function () {
    $agent = User::factory()->agent()->create();
    $client = mcpClient();
    $token = Token::query()->forceCreate([
        'id' => Str::random(80),
        'user_id' => $agent->id,
        'client_id' => $client->getKey(),
        'scopes' => ['mcp:use'],
        'revoked' => false,
        'expires_at' => now()->addDay(),
    ]);

    $this->actingAs($agent)->get(route('connected-apps.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page->has('apps', 1)->where('apps.0.name', 'Claude'));

    $this->actingAs($agent)->delete(route('connected-apps.destroy', $client->getKey()))->assertRedirect();

    expect($token->refresh()->revoked)->toBeTrue();
    $this->actingAs(User::factory()->create())->get(route('connected-apps.index'))->assertForbidden();
});
