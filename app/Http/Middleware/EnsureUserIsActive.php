<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ends the session of someone deactivated while signed in, and refuses their API calls.
 */
class EnsureUserIsActive
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User || ! $user->isDeactivated()) {
            return $next($request);
        }

        if (! $request->hasSession() || $request->expectsJson()) {
            abort(Response::HTTP_FORBIDDEN, __('This account has been deactivated.'));
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('status', __('This account has been deactivated.'));
    }
}
