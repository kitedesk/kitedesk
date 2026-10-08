<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Passport\RefreshToken;
use Laravel\Passport\Token;

/**
 * AI apps (MCP clients) a staff member connected over OAuth, and disconnecting them.
 */
class ConnectedAppController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('settings/connected-apps', [
            'apps' => $this->activeTokens($request)
                ->with('client')
                ->latest()
                ->get()
                ->groupBy('client_id')
                ->map(fn (Collection $tokens): array => [
                    'id' => (string) $tokens->first()->client_id,
                    'name' => $tokens->first()->client->name ?? __('Unknown app'),
                    'connected_at' => $tokens->last()->created_at?->toIso8601String(),
                    'last_active_at' => $tokens->first()->created_at?->toIso8601String(),
                ])
                ->values()
                ->all(),
        ]);
    }

    /**
     * Revoke every token (and refresh token) the app holds for this person; it must ask again.
     */
    public function destroy(Request $request, string $client): RedirectResponse
    {
        $tokens = Token::query()->where('user_id', $request->user()->id)->where('client_id', $client)->pluck('id');

        Token::query()->whereKey($tokens)->update(['revoked' => true]);
        RefreshToken::query()->whereIn('access_token_id', $tokens)->update(['revoked' => true]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('App disconnected.')]);

        return back();
    }

    /**
     * Tokens the app can still use: unexpired, or renewable through a live refresh token.
     *
     * @return Builder<Token>
     */
    private function activeTokens(Request $request): Builder
    {
        return Token::query()
            ->where('user_id', $request->user()->id)
            ->where('revoked', false)
            ->where(fn (Builder $active) => $active
                ->where('expires_at', '>', now())
                ->orWhereHas('refreshToken', fn (Builder $refresh) => $refresh->where('revoked', false)->where('expires_at', '>', now())));
    }
}
