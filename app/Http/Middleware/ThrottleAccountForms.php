<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rate limits Fortify's public account forms (password reset and registration), whose routes
 * the package registers without a throttle.
 */
class ThrottleAccountForms
{
    /**
     * Route names => named rate limiter (see FortifyServiceProvider).
     */
    private const array LIMITED_ROUTES = [
        'password.email' => 'password-reset',
        'password.update' => 'password-reset',
        'register.store' => 'registration',
    ];

    public function __construct(private ThrottleRequests $throttle) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $limiter = self::LIMITED_ROUTES[$request->route()?->getName() ?? ''] ?? null;

        if ($limiter === null || ! $request->isMethod('POST')) {
            return $next($request);
        }

        return $this->throttle->handle($request, $next, $limiter);
    }
}
