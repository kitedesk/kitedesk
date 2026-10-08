<?php

namespace App\Http\Controllers\Agent;

use App\Domain\Tickets\Models\Ticket;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Mark tickets as related to each other.
 */
class TicketLinkController extends Controller
{
    public function store(Request $request, Ticket $ticket): RedirectResponse
    {
        $request->validate([
            'linked_id' => ['required', 'integer', Rule::exists('tickets', 'id'), Rule::notIn([$ticket->id])],
        ]);

        $linked = Ticket::query()->findOrFail($request->integer('linked_id'));

        Gate::authorize('link', [$ticket, $linked]);

        $ticket->linkTo($linked);

        return back();
    }

    public function destroy(Ticket $ticket, Ticket $linked): RedirectResponse
    {
        Gate::authorize('link', [$ticket, $linked]);

        $ticket->unlinkFrom($linked);

        return back();
    }
}
