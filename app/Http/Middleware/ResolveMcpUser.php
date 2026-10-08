<?php

namespace App\Http\Middleware;

use App\Domain\Ai\Mcp\McpAccess;
use App\Domain\Ai\Models\McpUser;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * After an OAuth (Passport) sign-in on the MCP server, continue as the real User so roles,
 * policies and activity work as everywhere else, and remember the call came through OAuth.
 */
class ResolveMcpUser
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // `auth:sanctum,mcp` makes the guard that signed the request in the default one.
        if (Auth::getDefaultDriver() === 'mcp') {
            $connected = Auth::guard('mcp')->user();
            abort_unless($connected instanceof McpUser && $connected->tokenCan('mcp:use'), Response::HTTP_FORBIDDEN);

            $user = User::query()->whereKey($connected->getKey())->firstOrFail();
            Auth::guard('mcp')->setUser($user);
            $request->setUserResolver(fn (): User => $user);
            $request->attributes->set(McpAccess::OAUTH, true);
        }

        return $next($request);
    }
}
