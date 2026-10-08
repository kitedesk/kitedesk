<?php

namespace App\Http\Controllers\Agent;

use App\Domain\Tickets\Actions\MergeTickets;
use App\Domain\Tickets\Models\Ticket;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Merge a duplicate ticket into another one.
 */
class TicketMergeController extends Controller
{
    public function __invoke(Request $request, Ticket $ticket, MergeTickets $mergeTickets): RedirectResponse
    {
        $request->validate([
            'target_id' => ['required', 'integer', Rule::exists('tickets', 'id'), Rule::notIn([$ticket->id])],
        ]);

        $target = Ticket::query()->findOrFail($request->integer('target_id'));

        Gate::authorize('merge', [$ticket, $target]);

        $mergeTickets->handle($ticket, $target, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Ticket :source merged into :target.', ['source' => $ticket->reference(), 'target' => $target->reference()])]);

        return to_route('agent.tickets.show', $target);
    }
}
