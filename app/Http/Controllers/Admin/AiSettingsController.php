<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Ai\Agents\ConnectionCheck;
use App\Domain\Ai\Support\AiSettings;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveAiSettingsRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Passport\Token;
use Throwable;

/**
 * The AI assistant's connection and features, and the MCP server.
 */
class AiSettingsController extends Controller
{
    public function edit(): Response
    {
        $settings = AiSettings::current();

        return Inertia::render('admin/ai/edit', [
            'settings' => [
                'enabled' => $settings->enabled,
                'base_url' => $settings->baseUrl ?? '',
                'api_key' => '',
                'model' => $settings->model ?? '',
                'summaries' => $settings->summaries,
                'drafts' => $settings->drafts,
                'improve' => $settings->improve,
                'mcp_enabled' => $settings->mcpEnabled,
                'instructions' => $settings->instructions ?? '',
                'context_messages' => $settings->contextMessages,
            ],
            'hasApiKey' => $settings->apiKey !== null,
            'mcpUrl' => route('mcp'),
            'connectedApps' => $this->connectedApps(),
        ]);
    }

    public function update(SaveAiSettingsRequest $request): RedirectResponse
    {
        AiSettings::current()->update($request->settings())->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('AI settings saved.')]);

        return back();
    }

    /**
     * Send a tiny prompt with the settings on the form (saved or not; a blank key uses the saved one).
     */
    public function test(Request $request): RedirectResponse
    {
        $input = $request->validate([
            'base_url' => ['required', 'url:http,https', 'max:255'],
            'api_key' => ['nullable', 'string', 'max:500'],
            'model' => ['required', 'string', 'max:150'],
        ]);

        $saved = AiSettings::current();
        $settings = new AiSettings(
            enabled: true,
            baseUrl: rtrim($input['base_url'], '/'),
            apiKey: filled($input['api_key'] ?? null) ? $input['api_key'] : $saved->apiKey,
            model: $input['model'],
        );

        try {
            $reply = (new ConnectionCheck($settings))->prompt('Are you there?')->text;
        } catch (Throwable $exception) {
            Log::info('AI connection test failed.', ['exception' => $exception]);

            Inertia::flash('toast', ['type' => 'error', 'message' => __('The connection failed: :error', [
                'error' => Str::limit($exception->getMessage(), 300),
            ])]);

            return back();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Connected. The model replied: :reply', [
            'reply' => Str::limit(trim($reply), 80),
        ])]);

        return back();
    }

    /**
     * Staff who have connected an AI app over OAuth, with the apps and when they connected.
     *
     * @return array<int, array{user: string, app: string, connected_at: string|null}>
     */
    private function connectedApps(): array
    {
        return Token::query()
            ->where('revoked', false)
            ->where('expires_at', '>', now())
            ->with('client')
            ->latest()
            ->limit(50)
            ->get()
            ->unique(fn (Token $token): string => $token->user_id.'|'.$token->client_id)
            ->map(fn (Token $token): array => [
                'user' => User::query()->find($token->user_id)->name ?? __('Deleted user'),
                'app' => $token->client->name ?? __('Unknown app'),
                'connected_at' => $token->created_at?->toIso8601String(),
            ])
            ->values()
            ->all();
    }
}
