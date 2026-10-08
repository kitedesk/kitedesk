<?php

namespace App\Http\Controllers\Agent;

use App\Domain\Tickets\Actions\AddMessage;
use App\Domain\Tickets\Enums\TicketChannel;
use App\Domain\Tickets\Models\Ticket;
use App\Http\Controllers\Controller;
use App\Http\Requests\Agent\StoreTicketMessageRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class TicketMessageController extends Controller
{
    public function store(StoreTicketMessageRequest $request, Ticket $ticket, AddMessage $addMessage): RedirectResponse
    {
        $isInternal = $request->boolean('is_internal');

        $addMessage->handle(
            $ticket,
            $request->user(),
            $request->string('body')->toString(),
            isInternal: $isInternal,
            channel: TicketChannel::Agent,
            attachments: $request->attachments(),
            statusAfter: $request->statusAfter(),
            metadata: $request->boolean('ai_assisted') ? ['ai_assisted' => true] : [],
            secretTokens: $request->secretTokens(),
        );

        if (! $isInternal && $request->boolean('remember_status') && $request->filled('ticket_status_id')) {
            $this->rememberReplyStatus($request->user(), $request->integer('ticket_status_id'));
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => $isInternal ? __('Internal note added.') : __('Reply sent.')]);

        return back();
    }

    /**
     * Keep the status the agent chose from the "Submit as" menu as their default.
     */
    private function rememberReplyStatus(User $user, int $statusId): void
    {
        if (($user->preferences['reply_status_id'] ?? null) === $statusId) {
            return;
        }

        $user->forceFill(['preferences' => [...($user->preferences ?? []), 'reply_status_id' => $statusId]])->save();
    }
}
