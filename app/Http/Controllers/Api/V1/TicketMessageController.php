<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Tickets\Actions\AddMessage;
use App\Domain\Tickets\Enums\TicketChannel;
use App\Domain\Tickets\Models\Ticket;
use App\Http\Requests\Api\V1\StoreTicketMessageRequest;
use App\Http\Resources\Api\V1\MessageResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

/**
 * @tags Ticket messages
 */
class TicketMessageController extends ApiController
{
    /**
     * List a ticket's messages.
     *
     * Includes internal notes (flagged with `is_internal`), oldest first, paginated
     * (`per_page` up to 100, default 50).
     */
    public function index(Request $request, Ticket $ticket): AnonymousResourceCollection
    {
        Gate::authorize('view', $ticket);

        return MessageResource::collection(
            $ticket->messages()->oldest()->oldest('id')->with(['author', 'media'])
                ->paginate($this->perPage($request, 50))
                ->withQueryString(),
        );
    }

    /**
     * Add a reply or internal note.
     *
     * The token owner is the author. Public replies follow the same status and SLA rules as the agent workspace.
     */
    public function store(StoreTicketMessageRequest $request, Ticket $ticket, AddMessage $addMessage): JsonResponse
    {
        $message = $addMessage->handle(
            $ticket,
            $request->user(),
            $request->string('body')->toString(),
            isInternal: $request->boolean('is_internal'),
            channel: TicketChannel::Api,
            attachments: $request->attachments(),
            statusAfter: $request->statusAfter(),
        );

        return (new MessageResource($message->load(['author', 'media'])))->response()->setStatusCode(201);
    }
}
