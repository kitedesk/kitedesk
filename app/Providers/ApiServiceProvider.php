<?php

namespace App\Providers;

use App\Domain\Entitlements\Enums\Feature;
use App\Domain\Entitlements\Support\PlanLimits;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\PersonalAccessToken;

class ApiServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Per API token; OAuth (MCP) callers are limited per person.
        RateLimiter::for('api', function (Request $request): Limit {
            $token = $request->user()?->currentAccessToken();

            return Limit::perMinute(120)->by(match (true) {
                $token instanceof PersonalAccessToken => 'token:'.$token->getKey(),
                $request->user() !== null => 'user:'.$request->user()->getAuthIdentifier(),
                default => 'ip:'.$request->ip(),
            });
        });

        // The AI assistant on ticket pages: each call is a paid request to the provider.
        RateLimiter::for('ai', fn (Request $request): Limit => Limit::perMinute(20)->by('user:'.$request->user()?->getAuthIdentifier()));

        // Any staff member can hold a token, so any of them can read the reference.
        Gate::define('viewApiDocs', fn (?User $user): bool => $user?->isStaff() === true && PlanLimits::allows(Feature::Api));
    }
}
