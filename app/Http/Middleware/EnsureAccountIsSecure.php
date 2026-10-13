<?php

namespace App\Http\Middleware;

use App\Domain\Accounts\Support\PasswordPolicy;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Holds people at the door until their account meets the password policy: a new password when
 * theirs expired or an admin asked for one, and two-factor authentication when staff need it.
 */
class EnsureAccountIsSecure
{
    /**
     * Pages they can still reach: signing out, and what it takes to meet the policy.
     *
     * @var list<string>
     */
    private const array ALLOWED_ROUTES = [
        'logout',
        'password.change', 'password.change.update',
        'security.edit', 'user-password.update',
        'password.confirm', 'password.confirm.store', 'password.confirmation',
        'two-factor.*', 'passkey.*', 'verification.*',
        'branding.asset', 'avatars.show',
    ];

    /**
     * @var array<string, Closure(Request): bool>
     */
    private static array $skipWhen = [];

    /**
     * Let requests through when the callback says so (e.g. KiteDesk support signed in as
     * someone). Registering the same name again replaces it.
     *
     * @param  Closure(Request): bool  $callback
     */
    public static function skipWhen(string $name, Closure $callback): void
    {
        self::$skipWhen[$name] = $callback;
    }

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User || ! $request->hasSession() || $request->routeIs(...self::ALLOWED_ROUTES) || $this->skipped($request)) {
            return $next($request);
        }

        $policy = PasswordPolicy::current();

        if ($policy->requiresNewPassword($user)) {
            abort_if($request->expectsJson(), Response::HTTP_FORBIDDEN, __('Choose a new password to continue.'));

            return redirect()->route('password.change');
        }

        if ($policy->requiresTwoFactor($user)) {
            abort_if($request->expectsJson(), Response::HTTP_FORBIDDEN, __('Turn on two-factor authentication to continue.'));

            return redirect()->route('security.edit');
        }

        return $next($request);
    }

    private function skipped(Request $request): bool
    {
        foreach (self::$skipWhen as $callback) {
            if ($callback($request)) {
                return true;
            }
        }

        return false;
    }
}
