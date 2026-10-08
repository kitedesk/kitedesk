<?php

namespace App\Domain\Api\Support;

use App\Domain\Api\Actions\CreateApiToken;
use App\Domain\Api\Enums\ApiAbility;
use App\Models\User;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * What the API token pages (Settings and Admin center) show.
 */
class ApiTokenList
{
    /**
     * @param  Builder<PersonalAccessToken>  $tokens
     * @param  Closure(PersonalAccessToken): string  $destroyUrl
     * @return list<array<string, mixed>>
     */
    public static function present(Builder $tokens, Closure $destroyUrl): array
    {
        $tokens = $tokens
            ->where('tokenable_type', (new User)->getMorphClass())
            ->with('tokenable')
            ->latest()
            ->latest('id')
            ->get();

        $creators = User::query()->whereKey($tokens->pluck('created_by_id')->filter()->unique()->all())->get(['id', 'name'])->keyBy('id');

        return array_values($tokens
            ->map(fn (PersonalAccessToken $token): array => [
                'id' => $token->id,
                'name' => $token->name,
                'abilities' => $token->abilities,
                'owner' => $token->tokenable instanceof User
                    ? ['id' => $token->tokenable->id, 'name' => $token->tokenable->name, 'email' => $token->tokenable->email]
                    : null,
                'created_by' => $creators->get($token->getAttribute('created_by_id'))?->only(['id', 'name']),
                'last_used_at' => $token->last_used_at?->toIso8601String(),
                'expires_at' => $token->expires_at?->toIso8601String(),
                'created_at' => $token->created_at?->toIso8601String(),
                'destroy_url' => $destroyUrl($token),
            ])
            ->all());
    }

    /**
     * @return array{id: int, name: string, email: string, abilities: list<string>}
     */
    public static function owner(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'abilities' => array_map(fn (ApiAbility $ability): string => $ability->value, ApiAbility::availableTo($user)),
        ];
    }

    /**
     * @return array{abilities: list<array{value: string, label: string}>, expiryDays: list<int>}
     */
    public static function formOptions(): array
    {
        return ['abilities' => ApiAbility::options(), 'expiryDays' => CreateApiToken::EXPIRY_DAYS];
    }
}
