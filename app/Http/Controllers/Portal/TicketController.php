<?php

namespace App\Http\Controllers\Portal;

use App\Domain\Tickets\Actions\CreateTicket;
use App\Domain\Tickets\Enums\TicketChannel;
use App\Domain\Tickets\Enums\TicketStatus;
use App\Domain\Tickets\Models\Ticket;
use App\Domain\Tickets\Support\SatisfactionSurvey;
use App\Domain\Tickets\Support\TicketCatalog;
use App\Domain\Tickets\Support\TicketThread;
use App\Http\Controllers\Controller;
use App\Http\Requests\Portal\StoreTicketRequest;
use App\Http\Resources\TicketMessageResource;
use App\Http\Resources\TicketResource;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class TicketController extends Controller
{
    public function index(Request $request): Response
    {
        $status = in_array($request->string('status')->toString(), ['solved', 'shared'], true)
            ? $request->string('status')->toString()
            : 'open';

        $tickets = ($status === 'shared' ? $request->user()->collaboratingTickets() : $request->user()->requestedTickets())
            ->when($status === 'solved', fn ($query) => $query->whereIn('status', [TicketStatus::Solved, TicketStatus::Closed]))
            ->when($status === 'open', fn ($query) => $query->unresolved())
            ->with(['assignee', 'category.parent'])
            ->withCount(['messages' => fn ($query) => $query->public()])
            ->latest('tickets.updated_at')
            ->latest('tickets.id')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('portal/tickets/index', [
            'status' => $status,
            'tickets' => TicketResource::collection($tickets),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('portal/tickets/create', [
            'categories' => TicketCatalog::categories(customerFacing: true),
            'forms' => TicketCatalog::forms(customerFacing: true),
            'defaultFormId' => TicketCatalog::defaultFormId(),
        ]);
    }

    public function store(StoreTicketRequest $request, CreateTicket $createTicket): RedirectResponse
    {
        $ticket = $createTicket->handle(
            $request->user(),
            [
                'subject' => $request->string('subject')->toString(),
                'body' => $request->string('body')->toString(),
                'category_id' => $request->validated('category_id'),
                'custom_fields' => $request->customFields(),
                'attachments' => $request->attachments(),
            ],
            TicketChannel::Portal,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Thanks! Your request :number was received.', ['number' => $ticket->reference()])]);

        return to_route('portal.tickets.show', $ticket);
    }

    public function show(Request $request, Ticket $ticket): Response
    {
        Gate::authorize('view', $ticket);

        $ticket->load([
            'assignee',
            'category.parent',
            'collaborators',
            'satisfactionRating',
        ]);
        $everything = $request->boolean('thread_all');

        return Inertia::render('portal/tickets/show', [
            'ticket' => (new TicketResource($ticket))->resolve($request),
            'messages' => TicketMessageResource::collection(TicketThread::messages($ticket, publicOnly: true, everything: $everything))->resolve($request),
            'earlierMessages' => TicketThread::earlierCount($ticket, publicOnly: true, everything: $everything),
            'canReply' => $request->user()->can('reply', $ticket),
            'satisfaction' => SatisfactionSurvey::current()->formFor($ticket, $request->user()),
        ]);
    }
}
