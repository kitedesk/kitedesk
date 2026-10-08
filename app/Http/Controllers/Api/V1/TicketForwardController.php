<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Accounts\Models\Group;
use App\Domain\Tickets\Actions\ForwardTicket;
use App\Domain\Tickets\Models\Ticket;
use App\Http\Requests\Agent\ForwardTicketRequest;
use App\Http\Resources\Api\V1\TicketResource;
use App\Models\User;

/**
 * @tags Tickets
 */
class TicketForwardController extends ApiController
{
    /**
     * Forward a ticket.
     *
     * Hand it to one agent (`assignee_id`) or to a group's queue (`group_id`), with an optional
     * internal `note` for whoever picks it up.
     */
    public function __invoke(ForwardTicketRequest $request, Ticket $ticket, ForwardTicket $forwardTicket): TicketResource
    {
        $target = $request->filled('group_id')
            ? Group::query()->findOrFail($request->integer('group_id'))
            : User::query()->findOrFail($request->integer('assignee_id'));

        $forwardTicket->handle($ticket, $target, $request->user(), $request->string('note')->toString());

        return new TicketResource($ticket->refresh());
    }
}
