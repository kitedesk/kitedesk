<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Tickets\Models\Ticket;
use App\Http\Resources\Api\V1\TicketResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Related tickets. Links work both ways: linking A to B also lists A on B.
 *
 * @tags Tickets
 */
class TicketLinkController extends ApiController
{
    /**
     * List linked tickets.
     *
     * Only tickets the token owner can see are listed.
     */
    public function index(Request $request, Ticket $ticket): AnonymousResourceCollection
    {
        Gate::authorize('view', $ticket);

        return TicketResource::collection(
            $ticket->linkedTickets()->visibleTo($request->user())->with(TicketResource::RELATIONS)->orderBy('tickets.id')->get(),
        );
    }

    /**
     * Link a ticket to another.
     */
    public function store(Request $request, Ticket $ticket): AnonymousResourceCollection
    {
        $request->validate([
            /** The ticket to link to. */
            'linked_id' => ['required', 'integer', Rule::exists('tickets', 'id'), Rule::notIn([$ticket->id])],
        ]);

        $linked = Ticket::query()->findOrFail($request->integer('linked_id'));

        Gate::authorize('link', [$ticket, $linked]);

        $ticket->linkTo($linked);

        return $this->index($request, $ticket);
    }

    /**
     * Remove a link between two tickets.
     */
    public function destroy(Ticket $ticket, Ticket $linked): Response
    {
        Gate::authorize('link', [$ticket, $linked]);

        $ticket->unlinkFrom($linked);

        return response()->noContent();
    }
}
