<?php

namespace App\Http\Controllers\Agent;

use App\Domain\Accounts\Models\Group;
use App\Domain\Tickets\Actions\ForwardTicket;
use App\Domain\Tickets\Models\Ticket;
use App\Http\Controllers\Controller;
use App\Http\Requests\Agent\ForwardTicketRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/**
 * Hand a ticket over to another agent or group, with an optional note.
 */
class TicketForwardController extends Controller
{
    public function __invoke(ForwardTicketRequest $request, Ticket $ticket, ForwardTicket $forwardTicket): RedirectResponse
    {
        $target = $request->filled('group_id')
            ? Group::query()->findOrFail($request->integer('group_id'))
            : User::query()->findOrFail($request->integer('assignee_id'));

        $forwardTicket->handle($ticket, $target, $request->user(), $request->string('note')->toString());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Ticket :number forwarded to :name.', ['number' => $ticket->reference(), 'name' => $target->name])]);

        return back();
    }
}
