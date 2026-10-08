<?php

namespace App\Http\Controllers\Agent;

use App\Domain\Accounts\Enums\Permission;
use App\Domain\Accounts\Models\Group;
use App\Domain\Ai\Support\AiSettings;
use App\Domain\Support\EnumOptions;
use App\Domain\Tickets\Actions\CreateTicket;
use App\Domain\Tickets\Actions\DeleteTicket;
use App\Domain\Tickets\Actions\UpdateTicket;
use App\Domain\Tickets\Enums\TicketChannel;
use App\Domain\Tickets\Enums\TicketPriority;
use App\Domain\Tickets\Enums\TicketStatus;
use App\Domain\Tickets\Enums\TicketType;
use App\Domain\Tickets\Models\CannedResponse;
use App\Domain\Tickets\Models\SavedView;
use App\Domain\Tickets\Models\Tag;
use App\Domain\Tickets\Models\Ticket;
use App\Domain\Tickets\Support\BoardPreferences;
use App\Domain\Tickets\Support\CustomStatuses;
use App\Domain\Tickets\Support\TicketBoard;
use App\Domain\Tickets\Support\TicketCatalog;
use App\Domain\Tickets\Support\TicketFilters;
use App\Domain\Tickets\Support\TicketThread;
use App\Domain\Tickets\Support\TicketViews;
use App\Domain\Workflows\Models\Workflow;
use App\Domain\Workflows\Models\WorkflowRun;
use App\Http\Controllers\Controller;
use App\Http\Requests\Agent\StoreTicketRequest;
use App\Http\Requests\Agent\UpdateTicketRequest;
use App\Http\Resources\TicketMessageResource;
use App\Http\Resources\TicketResource;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Activitylog\Models\Activity;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class TicketController extends Controller
{
    /**
     * Relations each ticket in the queue (list row or board card) shows.
     */
    private const array QUEUE_RELATIONS = ['requester', 'assignee', 'group', 'category.parent', 'tags', 'latestMessage'];

    public function index(Request $request): Response
    {
        $user = $request->user();
        $view = TicketViews::resolve($request->string('view')->toString(), $user);
        $savedView = TicketViews::savedView($view, $user);
        $preferences = BoardPreferences::for($user);
        $requestedLayout = $request->string('layout')->toString();
        $layout = in_array($requestedLayout, BoardPreferences::LAYOUTS, true) ? $requestedLayout : ($savedView->layout ?? $preferences->layout);

        return Inertia::render('agent/tickets/index', [
            'view' => $view,
            'layout' => $layout,
            'savedView' => $savedView === null ? null : [
                'id' => $savedView->id,
                'name' => $savedView->name,
                'filters' => $savedView->filters,
                'layout' => $savedView->layout,
                'is_shared' => $savedView->is_shared,
                'manageable' => $savedView->isManageableBy($user),
            ],
            'tickets' => fn () => $layout === 'list'
                ? TicketResource::collection($this->queue($request, $view, $savedView)->with(self::QUEUE_RELATIONS)->paginate(25)->withQueryString())
                : null,
            'board' => fn () => $layout === 'board'
                ? $this->board($request, $view, $savedView, BoardPreferences::for($user))
                : null,
            'boardPreferences' => fn () => BoardPreferences::for($user)->toArray(),
            // Always an object, so the page never reads `sort`/`filter` off an empty JSON array.
            'filters' => [
                'filter' => (object) array_filter((array) $request->input('filter', []), is_string(...)),
                'sort' => $request->filled('sort') ? $request->string('sort')->toString() : null,
            ],
            'options' => fn () => $this->options(),
        ]);
    }

    /**
     * More tickets for one board lane ("Load more").
     */
    public function boardLane(Request $request): JsonResponse
    {
        $user = $request->user();
        $view = TicketViews::resolve($request->string('view')->toString(), $user);
        $groupBy = BoardPreferences::for($user)->groupBy;
        $lane = $request->string('lane')->toString();

        abort_unless(TicketBoard::isValidKey($groupBy, $lane), 404);

        $tickets = TicketBoard::tickets(
            $this->queue($request, $view, TicketViews::savedView($view, $user))->getEloquentBuilder(),
            $groupBy,
            $lane,
            max(0, $request->integer('offset')),
            self::QUEUE_RELATIONS,
        );

        return response()->json([
            'tickets' => TicketResource::collection($tickets)->resolve($request),
            'has_more' => $tickets->count() === TicketBoard::PER_LANE,
        ]);
    }

    /**
     * The view's tickets with the queue's filters and sort applied.
     *
     * @return QueryBuilder<Ticket>
     */
    private function queue(Request $request, string $view, ?SavedView $savedView): QueryBuilder
    {
        return QueryBuilder::for(TicketViews::query($view, $request->user()))
            ->allowedFilters(
                AllowedFilter::exact('status'),
                AllowedFilter::exact('ticket_status_id'),
                AllowedFilter::exact('priority'),
                AllowedFilter::callback('assignee_id', fn (Builder $query, mixed $value) => TicketFilters::assignee($query, $value, $request->user())),
                AllowedFilter::exact('group_id'),
                AllowedFilter::callback('category_id', fn (Builder $query, mixed $value) => TicketFilters::category($query, $value)),
                AllowedFilter::callback('tag', fn (Builder $query, mixed $value) => TicketFilters::tag($query, $value)),
                AllowedFilter::callback('search', fn (Builder $query, mixed $value) => TicketFilters::search($query, $value)),
            )
            ->allowedSorts('updated_at', 'created_at', 'id')
            ->defaultSort($savedView->sort ?? '-updated_at');
    }

    /**
     * @return array<string, mixed>
     */
    private function board(Request $request, string $view, ?SavedView $savedView, BoardPreferences $preferences): array
    {
        $board = TicketBoard::build($this->queue($request, $view, $savedView)->getEloquentBuilder(), $preferences, self::QUEUE_RELATIONS);

        return [
            ...$board,
            'lanes' => array_map(fn (array $lane): array => [
                ...$lane,
                'tickets' => TicketResource::collection($lane['tickets'])->resolve($request),
            ], $board['lanes']),
        ];
    }

    public function create(Request $request): Response
    {
        return Inertia::render('agent/tickets/create', [
            'options' => fn () => $this->options(),
            'forms' => TicketCatalog::forms(),
            'defaultFormId' => TicketCatalog::defaultFormId(),
            'requester' => $request->integer('requester_id')
                ? User::query()->select(['id', 'name', 'email'])->find($request->integer('requester_id'))
                : null,
        ]);
    }

    public function store(StoreTicketRequest $request, CreateTicket $createTicket): RedirectResponse
    {
        $requester = $request->filled('requester_id')
            ? User::query()->findOrFail($request->integer('requester_id'))
            : User::query()->firstOrCreate(
                ['email' => $request->string('requester_email')->lower()->toString()],
                ['name' => $request->string('requester_name')->toString(), 'password' => str()->random(40)],
            );

        $ticket = $createTicket->handle(
            $requester,
            [
                'subject' => $request->string('subject')->toString(),
                'body' => $request->string('body')->toString(),
                'priority' => $request->validated('priority'),
                'type' => $request->validated('type'),
                'assignee_id' => $request->validated('assignee_id'),
                'group_id' => $request->validated('group_id'),
                'category_id' => $request->validated('category_id'),
                'tags' => array_values(array_map(strval(...), (array) $request->validated('tags', []))),
                'custom_fields' => $request->customFields(),
                'attachments' => $request->attachments(),
            ],
            TicketChannel::Agent,
            $request->user(),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Ticket :number created.', ['number' => $ticket->reference()])]);

        return to_route('agent.tickets.show', $ticket);
    }

    public function show(Request $request, Ticket $ticket): Response
    {
        $ticket->load([
            'requester.organization',
            'assignee',
            'group',
            'category.parent',
            'form.fields',
            'collaborators',
            'organization',
            'tags',
            'slaPolicy',
            'mergedInto',
            'satisfactionRating',
        ]);
        $satisfaction = $ticket->satisfactionRating;

        return Inertia::render('agent/tickets/show', [
            'ticket' => (new TicketResource($ticket))->resolve($request),
            'messages' => fn () => TicketMessageResource::collection(
                TicketThread::messages($ticket, publicOnly: false, everything: $request->boolean('thread_all')),
            )->resolve($request),
            'earlierMessages' => fn () => TicketThread::earlierCount($ticket, publicOnly: false, everything: $request->boolean('thread_all')),
            // The status the agent last picked from "Submit as", their default for replies.
            'replyStatusId' => fn () => $request->user()->preferences['reply_status_id'] ?? null,
            'signature' => fn () => $request->user()->signature,
            'requester' => [
                'id' => $ticket->requester->id,
                'name' => $ticket->requester->name,
                'email' => $ticket->requester->email,
                'avatar' => $ticket->requester->avatar,
                'job_title' => $ticket->requester->job_title,
                'phone' => $ticket->requester->phone,
                'timezone' => $ticket->requester->timezone,
                'organization' => $ticket->requester->organization?->only(['id', 'name']),
                'created_at' => $ticket->requester->created_at?->toIso8601String(),
            ],
            'requesterTickets' => Inertia::defer(fn () => TicketResource::collection(
                $ticket->requester->requestedTickets()->whereKeyNot($ticket->id)->latest()->limit(5)->get(),
            )->resolve($request)),
            'activity' => Inertia::defer(fn () => $this->activityFor($ticket)),
            'ticketFields' => TicketCatalog::fieldsForTicket($ticket),
            'satisfaction' => fn () => $satisfaction === null ? null : [
                'score' => $satisfaction->score,
                'comment' => $satisfaction->comment,
                'sent_at' => $satisfaction->sent_at?->toIso8601String(),
                'rated_at' => $satisfaction->rated_at?->toIso8601String(),
            ],
            'mergedInto' => $ticket->mergedInto ? ['id' => $ticket->mergedInto->id, 'number' => $ticket->mergedInto->reference(), 'subject' => $ticket->mergedInto->subject] : null,
            'linkedTickets' => fn () => $ticket->linkedTickets()->orderBy('tickets.id')->get(['tickets.id', 'tickets.number', 'subject', 'status', 'ticket_status_id'])
                ->map(fn (Ticket $linked): array => ['id' => $linked->id, 'number' => $linked->reference(), 'subject' => $linked->subject, 'status' => $linked->status->value, 'custom_status' => CustomStatuses::find($linked->ticket_status_id)?->toSummary()])
                ->values(),
            'cannedResponses' => Inertia::defer(fn () => CannedResponse::query()
                ->availableTo($request->user())
                ->orderBy('title')
                ->get(['id', 'title', 'body'])
                ->map->only(['id', 'title', 'body'])
                ->values()),
            'options' => fn () => $this->options(),
            'workflows' => fn () => [
                'manual' => $request->user()->can('runWorkflows', $ticket)
                    ? Workflow::runnableBy($request->user())->map(fn (Workflow $workflow): array => ['id' => $workflow->id, 'name' => $workflow->name])->all()
                    : [],
                'runs' => $ticket->workflowRuns()
                    ->with('workflow:id,name')
                    ->latest('id')
                    ->limit(10)
                    ->get()
                    ->map(fn (WorkflowRun $run): array => [
                        'id' => $run->id,
                        'workflow_id' => $run->workflow_id,
                        'workflow' => $run->workflow->name,
                        'status' => $run->status->value,
                        'status_label' => $run->status->label(),
                        'created_at' => $run->created_at?->toIso8601String(),
                    ])
                    ->all(),
            ],
            'can' => [
                'update' => $request->user()->can('update', $ticket),
                'reply' => $request->user()->can('reply', $ticket),
                'addInternalNote' => $request->user()->can('addInternalNote', $ticket),
                'forward' => $request->user()->can('forward', $ticket),
                'merge' => $request->user()->hasPermission(Permission::MergeTickets) && $request->user()->can('update', $ticket),
                'delete' => $request->user()->can('delete', $ticket),
                'useSecrets' => $request->user()->can('useSecrets', $ticket),
            ],
            // Which assistant features this agent can use here (all false when AI is off).
            'ai' => $request->user()->can('useAi', $ticket)
                ? AiSettings::current()->features()
                : ['summaries' => false, 'drafts' => false, 'improve' => false],
        ]);
    }

    public function update(UpdateTicketRequest $request, Ticket $ticket, UpdateTicket $updateTicket): RedirectResponse
    {
        $updateTicket->handle($ticket, $request->validated(), $request->user());

        return back();
    }

    public function destroy(Ticket $ticket, DeleteTicket $deleteTicket): RedirectResponse
    {
        Gate::authorize('delete', $ticket);

        $reference = $ticket->reference();
        $deleteTicket->handle($ticket);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Ticket :number deleted.', ['number' => $reference])]);

        return to_route('agent.tickets.index');
    }

    /**
     * Select options for ticket properties, shared by every agent ticket page.
     *
     * @return array<string, mixed>
     */
    private function options(): array
    {
        return [
            'statuses' => EnumOptions::for(TicketStatus::class),
            'customStatuses' => CustomStatuses::options(),
            'priorities' => EnumOptions::for(TicketPriority::class),
            'types' => EnumOptions::for(TicketType::class),
            'agents' => User::query()
                ->assignable()
                ->orderBy('name')
                ->get(['id', 'name', 'avatar_path'])
                ->map(fn (User $agent): array => ['id' => $agent->id, 'name' => $agent->name, 'avatar' => $agent->avatar]),
            'groups' => Group::query()->orderBy('name')->get(['id', 'name']),
            'categories' => TicketCatalog::categories(),
            'tags' => Tag::query()->orderBy('name')->limit(200)->pluck('name'),
        ];
    }

    /**
     * Audit trail of property changes, newest first.
     *
     * @return list<array<string, mixed>>
     */
    private function activityFor(Ticket $ticket): array
    {
        return array_values(Activity::query()
            ->forSubject($ticket)
            ->with('causer')
            ->latest()
            ->latest('id')
            ->limit(50)
            ->get()
            ->map(fn (Activity $activity): array => [
                'id' => $activity->id,
                'event' => $activity->event,
                'causer' => $activity->causer instanceof User ? $activity->causer->name : null,
                'workflow' => $activity->properties?->get('workflow'),
                'score' => $activity->event === 'rated' ? $activity->properties?->get('score') : null,
                'secret' => str_starts_with((string) $activity->event, 'secret_') ? $activity->properties?->get('secret') : null,
                'changes' => [
                    'old' => $activity->attribute_changes?->get('old') ?? [],
                    'new' => $activity->attribute_changes?->get('attributes') ?? [],
                ],
                'created_at' => $activity->created_at?->toIso8601String(),
            ])
            ->all());
    }
}
