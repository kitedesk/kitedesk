<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restricts the agent workspace to support staff.
 */
class EnsureUserIsStaff
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->isStaff(), Response::HTTP_FORBIDDEN);

        return $next($request);
    }
}
