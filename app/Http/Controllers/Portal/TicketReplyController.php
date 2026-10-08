<?php

namespace App\Http\Controllers\Portal;

use App\Domain\Tickets\Actions\AddMessage;
use App\Domain\Tickets\Enums\TicketChannel;
use App\Domain\Tickets\Enums\TicketStatus;
use App\Domain\Tickets\Models\Ticket;
use App\Http\Controllers\Controller;
use App\Http\Requests\Portal\StoreReplyRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class TicketReplyController extends Controller
{
    public function store(StoreReplyRequest $request, Ticket $ticket, AddMessage $addMessage): RedirectResponse
    {
        $addMessage->handle(
            $ticket,
            $request->user(),
            $request->string('body')->toString(),
            channel: TicketChannel::Portal,
            attachments: $request->attachments(),
            statusAfter: $request->boolean('mark_solved') ? TicketStatus::Solved : null,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Your reply was sent.')]);

        return back();
    }
}
