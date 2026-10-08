<?php

namespace App\Domain\Tickets\Support;

use App\Domain\Tickets\Models\Ticket;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

/**
 * Ticket access for people without a usable account: a signed link (valid for 30 days)
 * grants access to one ticket for the rest of the browser session.
 */
class GuestAccess
{
    private const string SESSION_KEY = 'guest_tickets';

    public static function enabled(): bool
    {
        return (bool) config('kitedesk.guest_tickets');
    }

    /**
     * Customers who never verified an email can't sign in to the portal, so they get magic links.
     */
    public static function needsLink(User $user): bool
    {
        return $user->isCustomer() && $user->email_verified_at === null;
    }

    public static function linkFor(Ticket $ticket, User $user): string
    {
        return URL::temporarySignedRoute('guest.tickets.access', now()->addDays(30), [
            'ticket' => $ticket->id,
            'user' => $user->id,
        ]);
    }

    /**
     * Where a customer should go to read the ticket: the portal, or a magic link.
     */
    public static function urlFor(Ticket $ticket, User $user): string
    {
        return self::needsLink($user) ? self::linkFor($ticket, $user) : route('portal.tickets.show', $ticket);
    }

    public static function grant(Request $request, Ticket $ticket, User $user): void
    {
        $request->session()->put(self::SESSION_KEY.'.'.$ticket->id, $user->id);
    }

    /**
     * The person this browser session acts as on the ticket, if access was granted.
     */
    public static function userFor(Request $request, Ticket $ticket): ?User
    {
        $userId = $request->session()->get(self::SESSION_KEY.'.'.$ticket->id);
        $user = is_int($userId) ? User::query()->find($userId) : null;

        return $user !== null && ! $user->isDeactivated() && $ticket->involves($user) ? $user : null;
    }
}
