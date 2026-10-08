<?php

namespace App\Http\Middleware;

use App\Domain\Ai\Support\AiSettings;
use App\Domain\Entitlements\Enums\Feature;
use App\Domain\Entitlements\Support\PlanLimits;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Hides the MCP server until an admin switches it on, and when the plan doesn't include it.
 */
class EnsureMcpEnabled
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(AiSettings::current()->mcpEnabled && PlanLimits::allows(Feature::Mcp), Response::HTTP_NOT_FOUND);

        return $next($request);
    }
}
