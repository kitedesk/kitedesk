<?php

namespace App\Http\Controllers\Settings;

use App\Domain\Api\Actions\CreateApiToken;
use App\Domain\Api\Support\ApiTokenList;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * A staff member's own REST API tokens. They act as that person, within what their role allows.
 */
class ApiTokenController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('settings/api-tokens', [
            'tokens' => ApiTokenList::present(
                $request->user()->tokens()->getQuery(),
                fn (PersonalAccessToken $token): string => route('api-tokens.destroy', $token),
            ),
            'owner' => ApiTokenList::owner($request->user()),
            ...ApiTokenList::formOptions(),
        ]);
    }

    public function store(Request $request, CreateApiToken $createToken): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'abilities' => ['required', 'array', 'min:1'],
            'abilities.*' => ['string'],
            'expires_in_days' => ['required', 'integer', Rule::in(CreateApiToken::EXPIRY_DAYS)],
        ]);

        $token = $createToken->handle($request->user(), $request->user(), $validated['name'], array_values($validated['abilities']), (int) $validated['expires_in_days']);

        Inertia::flash('newToken', ['name' => $validated['name'], 'plainText' => $token->plainTextToken]);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Token created. Copy it now — it will not be shown again.')]);

        return to_route('api-tokens.index');
    }

    public function destroy(Request $request, PersonalAccessToken $apiToken): RedirectResponse
    {
        abort_unless($apiToken->tokenable_type === $request->user()->getMorphClass() && (int) $apiToken->tokenable_id === $request->user()->id, 404);

        $apiToken->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Token revoked.')]);

        return to_route('api-tokens.index');
    }
}
