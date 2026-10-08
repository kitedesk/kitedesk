<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Tickets\Actions\CreateTicket;
use App\Domain\Tickets\Actions\DeleteTicket;
use App\Domain\Tickets\Actions\UpdateTicket;
use App\Domain\Tickets\Enums\TicketChannel;
use App\Domain\Tickets\Models\Ticket;
use App\Http\Requests\Api\V1\StoreTicketRequest;
use App\Http\Requests\Api\V1\UpdateTicketRequest;
use App\Http\Resources\Api\V1\TicketResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * @tags Tickets
 */
class TicketController extends ApiController
{
    /**
     * List tickets.
     *
     * Filter with `filter[status]`, `filter[priority]`, `filter[assignee_id]`, `filter[group_id]`,
     * `filter[category_id]`, `filter[requester_id]`, `filter[organization_id]` and `filter[updated_since]` (ISO 8601).
     * Sort with `sort=updated_at|-updated_at|created_at|-created_at|id|-id`.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $tickets = QueryBuilder::for(Ticket::query()->visibleTo($request->user()))
            ->allowedFilters(
                AllowedFilter::exact('status'),
                AllowedFilter::exact('priority'),
                AllowedFilter::exact('assignee_id'),
                AllowedFilter::exact('group_id'),
                AllowedFilter::exact('category_id'),
                AllowedFilter::exact('requester_id'),
                AllowedFilter::exact('organization_id'),
                $this->updatedSince(),
            )
            ->allowedSorts('updated_at', 'created_at', 'id')
            ->defaultSort('-updated_at')
            ->with(TicketResource::RELATIONS)
            ->paginate($this->perPage($request))
            ->withQueryString();

        return TicketResource::collection($tickets);
    }

    /**
     * Show a ticket.
     */
    public function show(Ticket $ticket): TicketResource
    {
        Gate::authorize('view', $ticket);

        return new TicketResource($ticket->load(TicketResource::RELATIONS));
    }

    /**
     * Create a ticket.
     *
     * The requester is either an existing user (`requester_id`) or identified by `requester.email`;
     * a customer account is created for unknown emails. `category_id` picks the form whose fields
     * are validated; when no `group_id` is given, routing rules choose the group.
     */
    public function store(StoreTicketRequest $request, CreateTicket $createTicket): JsonResponse
    {
        $requester = $request->filled('requester_id')
            ? User::query()->findOrFail($request->integer('requester_id'))
            : User::query()->firstOrCreate(
                ['email' => Str::lower($request->string('requester.email')->toString())],
                ['name' => $request->string('requester.name')->toString(), 'password' => Str::random(40)],
            );

        $ticket = $createTicket->handle($requester, [
            'subject' => $request->string('subject')->toString(),
            'body' => $request->string('body')->toString(),
            'priority' => $request->validated('priority'),
            'type' => $request->validated('type'),
            'assignee_id' => $request->validated('assignee_id'),
            'group_id' => $request->validated('group_id'),
            'category_id' => $request->validated('category_id'),
            'collaborator_ids' => array_values(array_map(intval(...), (array) $request->validated('collaborator_ids', []))),
            'tags' => array_values(array_map(strval(...), (array) $request->validated('tags', []))),
            'custom_fields' => $request->customFields(),
            'attachments' => $request->attachments(),
        ], TicketChannel::Api);

        return $this->show($ticket)->response()->setStatusCode(201);
    }

    /**
     * Update a ticket.
     *
     * Only the given properties are changed. `tags` replaces the full tag list.
     */
    public function update(UpdateTicketRequest $request, Ticket $ticket, UpdateTicket $updateTicket): TicketResource
    {
        $updateTicket->handle($ticket, $request->validated(), $request->user());

        return $this->show($ticket->refresh());
    }

    /**
     * Delete a ticket.
     *
     * The ticket and its messages and attachments are removed for good.
     */
    public function destroy(Ticket $ticket, DeleteTicket $deleteTicket): Response
    {
        Gate::authorize('delete', $ticket);

        $deleteTicket->handle($ticket);

        return response()->noContent();
    }
}
