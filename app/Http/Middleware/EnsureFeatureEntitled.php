<?php

namespace App\Http\Middleware;

use App\Domain\Entitlements\Enums\Feature;
use App\Domain\Entitlements\Support\PlanLimits;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * `entitlement:{feature}`: refuses routes of a feature the plan doesn't include.
 */
class EnsureFeatureEntitled
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        abort_unless(PlanLimits::allows(Feature::from($feature)), Response::HTTP_FORBIDDEN, __('Your plan does not include this feature.'));

        return $next($request);
    }
}
