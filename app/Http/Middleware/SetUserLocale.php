<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * Shows the interface in the language people chose in their profile. Without a choice, the
 * installation language applies; the browser's language is never used.
 */
class SetUserLocale
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $locale = $user instanceof User ? $user->preferredLocale() : null;

        if ($locale !== null) {
            App::setLocale($locale);
        }

        return $next($request);
    }
}
