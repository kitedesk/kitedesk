<?php

namespace App\Domain\Ai\Mcp;

use App\Domain\Tickets\Models\Ticket;
use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Gate;

/**
 * What an MCP caller may do. Sanctum API tokens carry abilities (tickets:read, tickets:write,
 * kb:read) chosen by the admin who issued them. OAuth tokens can't be narrowed (laravel/mcp has
 * a single scope), so they may read and write, limited only by the person's role.
 */
final class McpAccess
{
    /**
     * Request attribute set by ResolveMcpUser when the caller signed in through OAuth.
     */
    public const string OAUTH = 'mcp.oauth';

    public static function allows(?Authenticatable $user, string $ability): bool
    {
        if (! $user instanceof User) {
            return false;
        }

        if (request()->attributes->get(self::OAUTH) === true) {
            return true;
        }

        // The MCP route has no session, so the token is always a real API token.
        return $user->tokenCan($ability);
    }

    /**
     * The ticket with this reference (#1042, 1042 or its id), if the person may see it. A ticket
     * they can't see is reported the same as a missing one.
     */
    public static function ticket(User $user, string $reference): ?Ticket
    {
        $ticket = Ticket::findByReference($reference);

        return $ticket !== null && Gate::forUser($user)->allows('view', $ticket) ? $ticket : null;
    }
}
