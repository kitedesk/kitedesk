<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Tickets\Actions\UpdateTicket;
use App\Domain\Tickets\Models\Ticket;
use App\Http\Resources\Api\V1\TicketResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

/**
 * Add or remove one tag without sending the whole list (PATCH `tags` replaces it).
 *
 * @tags Tickets
 */
class TicketTagController extends ApiController
{
    /**
     * Add a tag to a ticket.
     */
    public function update(Request $request, Ticket $ticket, string $tag, UpdateTicket $updateTicket): TicketResource
    {
        Gate::authorize('update', $ticket);
        Validator::make(['tag' => $tag], ['tag' => ['string', 'max:50']])->validate();

        $updateTicket->handle($ticket, ['tags' => [...$this->tagNames($ticket), $tag]], $request->user());

        return new TicketResource($ticket->refresh());
    }

    /**
     * Remove a tag from a ticket.
     */
    public function destroy(Request $request, Ticket $ticket, string $tag, UpdateTicket $updateTicket): TicketResource
    {
        Gate::authorize('update', $ticket);

        $updateTicket->handle($ticket, ['tags' => array_values(array_diff($this->tagNames($ticket), [$tag]))], $request->user());

        return new TicketResource($ticket->refresh());
    }

    /**
     * @return list<string>
     */
    private function tagNames(Ticket $ticket): array
    {
        return array_values($ticket->tags()->pluck('name')->all());
    }
}
