<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Tickets\Actions\UpdateTicket;
use App\Domain\Tickets\Models\Ticket;
use App\Http\Requests\Agent\StoreTicketCollaboratorRequest;
use App\Http\Resources\Api\V1\TicketResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/**
 * @tags Tickets
 */
class TicketCollaboratorController extends ApiController
{
    /**
     * Copy someone on a ticket (CC).
     *
     * Give an existing user's `user_id`, or an `email` (and optional `name`); a customer account is
     * created for unknown emails. People copied on a ticket receive public replies.
     */
    public function store(StoreTicketCollaboratorRequest $request, Ticket $ticket, UpdateTicket $updateTicket): TicketResource
    {
        $user = $request->filled('user_id')
            ? User::query()->findOrFail($request->integer('user_id'))
            : User::query()->firstOrCreate(
                ['email' => $request->string('email')->lower()->toString()],
                ['name' => $request->string('name')->toString() ?: Str::before($request->string('email')->toString(), '@'), 'password' => Str::random(40)],
            );

        $updateTicket->handle($ticket, ['collaborator_ids' => [...$ticket->collaboratorIds(), $user->id]], $request->user());

        return new TicketResource($ticket->refresh());
    }

    /**
     * Stop copying someone on a ticket.
     */
    public function destroy(Request $request, Ticket $ticket, User $user, UpdateTicket $updateTicket): TicketResource
    {
        Gate::authorize('update', $ticket);

        $updateTicket->handle($ticket, ['collaborator_ids' => array_values(array_diff($ticket->collaboratorIds(), [$user->id]))], $request->user());

        return new TicketResource($ticket->refresh());
    }
}
