<?php

namespace App\Http\Controllers\Agent;

use App\Domain\Tickets\Actions\UpdateTicket;
use App\Domain\Tickets\Models\Ticket;
use App\Http\Controllers\Controller;
use App\Http\Requests\Agent\StoreTicketCollaboratorRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/**
 * Copy people on a ticket (CC). Unknown email addresses become customer accounts.
 */
class TicketCollaboratorController extends Controller
{
    public function store(StoreTicketCollaboratorRequest $request, Ticket $ticket, UpdateTicket $updateTicket): RedirectResponse
    {
        $user = $request->filled('user_id')
            ? User::query()->findOrFail($request->integer('user_id'))
            : User::query()->firstOrCreate(
                ['email' => $request->string('email')->lower()->toString()],
                ['name' => $request->string('name')->toString() ?: Str::before($request->string('email')->toString(), '@'), 'password' => Str::random(40)],
            );

        $updateTicket->handle($ticket, [
            'collaborator_ids' => [...$ticket->collaboratorIds(), $user->id],
        ], $request->user());

        return back();
    }

    public function destroy(Request $request, Ticket $ticket, User $user, UpdateTicket $updateTicket): RedirectResponse
    {
        Gate::authorize('update', $ticket);

        $updateTicket->handle($ticket, [
            'collaborator_ids' => array_values(array_diff($ticket->collaboratorIds(), [$user->id])),
        ], $request->user());

        return back();
    }
}
