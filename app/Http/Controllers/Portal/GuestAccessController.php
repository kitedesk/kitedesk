<?php

namespace App\Http\Controllers\Portal;

use App\Domain\Tickets\Models\Ticket;
use App\Domain\Tickets\Notifications\TicketAccessLink;
use App\Domain\Tickets\Support\GuestAccess;
use App\Http\Controllers\Controller;
use App\Http\Requests\Portal\CheckRequestRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "Check a request": emails a fresh magic link, and opens tickets from those links.
 */
class GuestAccessController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('guest/check');
    }

    /**
     * Always answers the same way, so the form can't be used to find out which tickets exist.
     */
    public function store(CheckRequestRequest $request): RedirectResponse
    {
        $ticket = Ticket::findByReference($request->string('ticket')->toString());
        $person = User::query()->where('email', Str::lower($request->string('email')->toString()))->first();

        if ($ticket !== null && $person !== null && $ticket->involves($person)) {
            $person->notify(new TicketAccessLink($ticket));
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('If that request exists, we just emailed you a link to it.')]);

        return back();
    }

    /**
     * Open a ticket from a signed link (the route's `signed` middleware checks it).
     */
    public function access(Request $request, Ticket $ticket, User $user): RedirectResponse
    {
        abort_unless($ticket->involves($user) && ! $user->isDeactivated(), 403);

        if ($request->user()?->is($user)) {
            return to_route('portal.tickets.show', $ticket);
        }

        GuestAccess::grant($request, $ticket, $user);

        return to_route('guest.tickets.show', $ticket);
    }
}
