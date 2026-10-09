<?php

namespace App\Http\Controllers\Agent;

use App\Domain\Accounts\Models\Group;
use App\Domain\Support\EnumOptions;
use App\Domain\Tickets\Actions\AddMessage;
use App\Domain\Tickets\Actions\CreateTicket;
use App\Domain\Tickets\Enums\TicketChannel;
use App\Domain\Tickets\Enums\TicketPriority;
use App\Domain\Tickets\Enums\TicketStatus;
use App\Domain\Tickets\Models\Ticket;
use App\Domain\Tickets\Support\TicketThread;
use App\Http\Controllers\Controller;
use App\Http\Requests\Agent\StoreInternalRequestRequest;
use App\Http\Requests\Portal\StoreReplyRequest;
use App\Http\Resources\TicketMessageResource;
use App\Http\Resources\TicketResource;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Internal requests an agent opened for another department: the agent is their requester and
 * follows them like a customer would, seeing the conversation but not the team's notes.
 */
class InternalRequestController extends Controller
{
    public function index(Request $request): Response
    {
        $status = $request->string('status')->toString() === 'solved' ? 'solved' : 'open';
        $user = $request->user();

        $tickets = Ticket::query()
            ->where('channel', TicketChannel::Internal)
            ->where(fn ($involved) => $involved
                ->where('requester_id', $user->id)
                ->orWhereHas('collaborators', fn ($collaborators) => $collaborators->whereKey($user->id)))
            ->when($status === 'solved', fn ($query) => $query->whereIn('status', [TicketStatus::Solved, TicketStatus::Closed]))
            ->when($status === 'open', fn ($query) => $query->unresolved())
            ->with(['assignee', 'group'])
            ->withCount(['messages' => fn ($query) => $query->public()])
            ->latest('tickets.updated_at')
            ->latest('tickets.id')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('agent/requests/index', [
            'status' => $status,
            'tickets' => TicketResource::collection($tickets),
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', Ticket::class);

        return Inertia::render('agent/requests/create', [
            'groups' => Group::query()->orderBy('name')->get(['id', 'name']),
            'priorities' => EnumOptions::for(TicketPriority::class),
        ]);
    }

    public function store(StoreInternalRequestRequest $request, CreateTicket $createTicket): RedirectResponse
    {
        $user = $request->user();

        $ticket = $createTicket->handle(
            $user,
            [
                'subject' => $request->string('subject')->toString(),
                'body' => $request->string('body')->toString(),
                'priority' => $request->validated('priority'),
                'group_id' => $request->integer('group_id'),
                'attachments' => $request->attachments(),
            ],
            TicketChannel::Internal,
            $user,
        );

        $related = $request->relatedTicket();

        if ($related !== null) {
            $ticket->linkTo($related);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Request :number sent to :group.', ['number' => $ticket->reference(), 'group' => $ticket->group?->name])]);

        return to_route('agent.requests.show', $ticket);
    }

    public function show(Request $request, Ticket $ticket): Response|RedirectResponse
    {
        Gate::authorize('view', $ticket);

        // The team working the request opens it in the agent workspace.
        if (! $ticket->isRequestingSide($request->user())) {
            return to_route('agent.tickets.show', $ticket);
        }

        $ticket->load(['assignee', 'group', 'collaborators']);
        $everything = $request->boolean('thread_all');

        return Inertia::render('agent/requests/show', [
            'ticket' => (new TicketResource($ticket))->resolve($request),
            'messages' => TicketMessageResource::collection(TicketThread::messages($ticket, publicOnly: true, everything: $everything))->resolve($request),
            'earlierMessages' => TicketThread::earlierCount($ticket, publicOnly: true, everything: $everything),
            'canReply' => $request->user()->can('reply', $ticket),
        ]);
    }

    public function reply(StoreReplyRequest $request, Ticket $ticket, AddMessage $addMessage): RedirectResponse
    {
        $addMessage->handle(
            $ticket,
            $request->user(),
            $request->string('body')->toString(),
            channel: TicketChannel::Internal,
            attachments: $request->attachments(),
            statusAfter: $request->boolean('mark_solved') ? TicketStatus::Solved : null,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Your reply was sent.')]);

        return back();
    }
}
