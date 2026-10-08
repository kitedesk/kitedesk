<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Lets staff into the admin center when their role grants at least one admin section;
 * each section's routes then check their own permission.
 */
class EnsureCanAccessAdmin
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->canAccessAdmin(), Response::HTTP_FORBIDDEN);

        return $next($request);
    }
}
