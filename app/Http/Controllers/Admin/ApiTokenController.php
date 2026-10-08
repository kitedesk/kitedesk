<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Api\Actions\CreateApiToken;
use App\Domain\Api\Support\ApiTokenList;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Every API token in the help desk. Admins can revoke any of them, and create integration tokens
 * for people who can't do more than they can.
 */
class ApiTokenController extends Controller
{
    public function index(Request $request): Response
    {
        $admin = $request->user();

        return Inertia::render('admin/api-tokens/index', [
            'tokens' => ApiTokenList::present(PersonalAccessToken::query(), fn (PersonalAccessToken $token): string => route('admin.api-tokens.destroy', $token)),
            'owners' => User::query()->staff()->active()->with(['roles.permissions', 'permissions'])->orderBy('name')->get()
                ->filter(fn (User $member): bool => CreateApiToken::mayIssueFor($admin, $member))
                ->map(fn (User $member): array => ApiTokenList::owner($member))
                ->values(),
            ...ApiTokenList::formOptions(),
        ]);
    }

    public function store(Request $request, CreateApiToken $createToken): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'user_id' => ['required', 'integer', Rule::exists('users', 'id')],
            'abilities' => ['required', 'array', 'min:1'],
            'abilities.*' => ['string'],
            'expires_in_days' => ['required', 'integer', Rule::in(CreateApiToken::EXPIRY_DAYS)],
        ]);

        $token = $createToken->handle(
            $request->user(),
            User::query()->whereKey($request->integer('user_id'))->firstOrFail(),
            $validated['name'],
            array_values($validated['abilities']),
            (int) $validated['expires_in_days'],
        );

        Inertia::flash('newToken', ['name' => $validated['name'], 'plainText' => $token->plainTextToken]);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Token created. Copy it now — it will not be shown again.')]);

        return to_route('admin.api-tokens.index');
    }

    public function destroy(PersonalAccessToken $apiToken): RedirectResponse
    {
        $apiToken->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Token revoked.')]);

        return to_route('admin.api-tokens.index');
    }
}
