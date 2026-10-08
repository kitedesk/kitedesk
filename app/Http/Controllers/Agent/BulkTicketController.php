<?php

namespace App\Http\Controllers\Agent;

use App\Domain\Tickets\Actions\UpdateTicket;
use App\Domain\Tickets\Models\Ticket;
use App\Http\Controllers\Controller;
use App\Http\Requests\Agent\BulkUpdateTicketsRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class BulkTicketController extends Controller
{
    public function update(BulkUpdateTicketsRequest $request, UpdateTicket $updateTicket): RedirectResponse
    {
        $changes = $request->safe()->except('ids');
        $updated = 0;

        Ticket::query()->whereKey($request->validated('ids'))->get()->each(function (Ticket $ticket) use ($request, $updateTicket, $changes, &$updated): void {
            if ($request->user()->cannot('update', $ticket)) {
                return;
            }

            $updateTicket->handle($ticket, $changes, $request->user());
            $updated++;
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => trans_choice(':count ticket updated.|:count tickets updated.', $updated)]);

        return back();
    }
}
