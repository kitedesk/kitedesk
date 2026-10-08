<?php

namespace App\Domain\Entitlements\Support;

use App\Domain\Entitlements\Contracts\Entitlements;
use App\Domain\Entitlements\Enums\Feature;
use App\Domain\Entitlements\Enums\Limit;
use Illuminate\Validation\ValidationException;

/**
 * Checks against the plan ({@see Entitlements}) for the places that add something countable,
 * plus what the frontend needs to hide or disable what the plan leaves out.
 */
class PlanLimits
{
    public static function allows(Feature $feature): bool
    {
        return app(Entitlements::class)->allows($feature);
    }

    /**
     * Whether adding one more would go over the plan.
     */
    public static function reached(Limit $limit): bool
    {
        $max = app(Entitlements::class)->limit($limit);

        return $max !== null && $limit->usage() >= $max;
    }

    /**
     * The message for a form field when the plan has no room for one more, else null.
     */
    public static function errorFor(Limit $limit): ?string
    {
        return self::reached($limit) ? $limit->message((int) app(Entitlements::class)->limit($limit)) : null;
    }

    /**
     * @throws ValidationException when the plan has no room for one more
     */
    public static function ensureRoomFor(Limit $limit, string $field): void
    {
        $error = self::errorFor($limit);

        if ($error !== null) {
            throw ValidationException::withMessages([$field => $error]);
        }
    }

    /**
     * Shared with every page: which features are included and how many of each the plan allows.
     *
     * @return array{features: array<string, bool>, limits: array<string, int|null>}
     */
    public static function sharedProps(): array
    {
        $entitlements = app(Entitlements::class);

        return [
            'features' => collect(Feature::cases())->mapWithKeys(fn (Feature $feature): array => [$feature->value => $entitlements->allows($feature)])->all(),
            'limits' => collect(Limit::cases())->mapWithKeys(fn (Limit $limit): array => [$limit->value => $entitlements->limit($limit)])->all(),
        ];
    }
}
