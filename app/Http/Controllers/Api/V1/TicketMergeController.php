<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Tickets\Actions\MergeTickets;
use App\Domain\Tickets\Models\Ticket;
use App\Http\Resources\Api\V1\TicketResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * @tags Tickets
 */
class TicketMergeController extends ApiController
{
    /**
     * Merge a ticket into another.
     *
     * The conversation moves to the target, the ticket's people are copied on it, and the ticket is closed. Returns the target.
     */
    public function __invoke(Request $request, Ticket $ticket, MergeTickets $mergeTickets): TicketResource
    {
        $request->validate([
            /** The ticket to merge into. */
            'target_id' => ['required', 'integer', Rule::exists('tickets', 'id'), Rule::notIn([$ticket->id])],
        ]);

        $target = Ticket::query()->findOrFail($request->integer('target_id'));

        Gate::authorize('merge', [$ticket, $target]);

        $mergeTickets->handle($ticket, $target, $request->user());

        return new TicketResource($target->refresh());
    }
}
