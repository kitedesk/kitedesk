<?php

namespace App\Http\Middleware;

use App\Domain\Api\Enums\ApiAbility;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * `api.ability:{ability}`: the token must carry the ability and its owner must still hold the
 * ability's permissions, so taking a permission away from a role also limits that person's tokens.
 */
class EnsureApiAbility
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string $ability): Response
    {
        $ability = ApiAbility::from($ability);
        $user = $request->user();

        abort_unless($user !== null && $user->tokenCan($ability->value), Response::HTTP_FORBIDDEN, __('This token does not have the :ability ability.', ['ability' => $ability->value]));
        abort_unless($ability->allowedFor($user), Response::HTTP_FORBIDDEN, __("The token owner's role does not allow this."));

        return $next($request);
    }
}
