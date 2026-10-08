<?php

namespace App\Http\Controllers\Portal;

use App\Domain\Mail\Enums\EmailTemplateEvent;
use App\Domain\Mail\Models\EmailTemplate;
use App\Domain\Tickets\Actions\AddMessage;
use App\Domain\Tickets\Actions\CreateTicket;
use App\Domain\Tickets\Enums\TicketChannel;
use App\Domain\Tickets\Enums\TicketStatus;
use App\Domain\Tickets\Models\Ticket;
use App\Domain\Tickets\Models\TicketMessage;
use App\Domain\Tickets\Notifications\TicketReceived;
use App\Domain\Tickets\Support\GuestAccess;
use App\Domain\Tickets\Support\SatisfactionSurvey;
use App\Domain\Tickets\Support\TicketCatalog;
use App\Domain\Tickets\Support\TicketThread;
use App\Http\Controllers\Controller;
use App\Http\Requests\Portal\StoreGuestReplyRequest;
use App\Http\Requests\Portal\StoreGuestTicketRequest;
use App\Http\Resources\TicketMessageResource;
use App\Http\Resources\TicketResource;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Requests from people without an account. They follow their request through emailed
 * magic links; a link grants access to one ticket for the browser session.
 */
class GuestTicketController extends Controller
{
    public function create(Request $request): Response|RedirectResponse
    {
        abort_unless(GuestAccess::enabled(), 404);

        if ($request->user() !== null) {
            return to_route('portal.tickets.create');
        }

        return Inertia::render('guest/tickets/create', [
            'categories' => TicketCatalog::categories(customerFacing: true),
            'forms' => TicketCatalog::forms(customerFacing: true),
            'defaultFormId' => TicketCatalog::defaultFormId(),
        ]);
    }

    public function store(StoreGuestTicketRequest $request, CreateTicket $createTicket): RedirectResponse
    {
        abort_unless(GuestAccess::enabled(), 404);

        $email = Str::lower($request->string('email')->toString());
        $requester = User::query()->where('email', $email)->first();
        $isNewPerson = $requester === null;

        // Deactivated people can't open requests; answer as for any existing account.
        if ($requester?->isDeactivated() === true) {
            Inertia::flash('toast', ['type' => 'success', 'message' => __('Thanks! We emailed you a link to follow your request.')]);

            return to_route('guest.check');
        }

        $requester ??= User::query()->create([
            'name' => $request->string('name')->toString(),
            'email' => $email,
            'password' => Str::random(40),
        ]);

        $ticket = $createTicket->handle($requester, [
            'subject' => $request->string('subject')->toString(),
            'body' => $request->string('body')->toString(),
            'category_id' => $request->validated('category_id'),
            'custom_fields' => $request->customFields(),
            'attachments' => $request->attachments(),
            // Anyone can type an existing account's email here; agents see the request wasn't signed in.
            'tags' => $isNewPerson ? [] : ['unverified_sender'],
        ], TicketChannel::Portal);

        // The "request received" auto-reply already carries the link; send it ourselves only when it's off.
        if (! EmailTemplate::for(EmailTemplateEvent::TicketReceived)->is_active) {
            $requester->notify(new TicketReceived($ticket));
        }

        // Someone typing an existing account's email must not see that account's tickets:
        // they get the link by email instead.
        if (! $isNewPerson) {
            Inertia::flash('toast', ['type' => 'success', 'message' => __('Thanks! We emailed you a link to follow request :number.', ['number' => $ticket->reference()])]);

            return to_route('guest.check');
        }

        GuestAccess::grant($request, $ticket, $requester);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Thanks! Your request :number was received.', ['number' => $ticket->reference()])]);

        return to_route('guest.tickets.show', $ticket);
    }

    public function show(Request $request, Ticket $ticket): Response|RedirectResponse
    {
        $guest = GuestAccess::userFor($request, $ticket);

        if ($guest === null) {
            Inertia::flash('toast', ['type' => 'info', 'message' => __('Your link has expired. Ask for a new one below.')]);

            return to_route('guest.check');
        }

        $ticket->load([
            'assignee',
            'category.parent',
            'satisfactionRating',
        ]);
        $everything = $request->boolean('thread_all');

        return Inertia::render('guest/tickets/show', [
            'ticket' => (new TicketResource($ticket))->resolve($request),
            'messages' => $this->withGuestAttachmentUrls($ticket, TicketMessageResource::collection(TicketThread::messages($ticket, publicOnly: true, everything: $everything))->resolve($request)),
            'earlierMessages' => TicketThread::earlierCount($ticket, publicOnly: true, everything: $everything),
            'guest' => ['id' => $guest->id, 'name' => $guest->name],
            'canReply' => $ticket->status !== TicketStatus::Closed,
            'satisfaction' => SatisfactionSurvey::current()->formFor($ticket, $guest),
        ]);
    }

    public function reply(StoreGuestReplyRequest $request, Ticket $ticket, AddMessage $addMessage): RedirectResponse
    {
        $guest = GuestAccess::userFor($request, $ticket);
        abort_if($guest === null, 403);

        $addMessage->handle(
            $ticket,
            $guest,
            $request->string('body')->toString(),
            channel: TicketChannel::Portal,
            attachments: $request->attachments(),
            statusAfter: $request->boolean('mark_solved') ? TicketStatus::Solved : null,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Your reply was sent.')]);

        return back();
    }

    public function attachment(Request $request, Ticket $ticket, Media $media): StreamedResponse
    {
        $message = $media->model;

        abort_unless(GuestAccess::userFor($request, $ticket) !== null, 403);
        abort_unless($message instanceof TicketMessage && $message->ticket_id === $ticket->id && ! $message->is_internal, 404);

        return response()->streamDownload(function () use ($media): void {
            $stream = $media->stream();
            fpassthru($stream);
            fclose($stream);
        }, $media->file_name, ['Content-Type' => $media->mime_type ?? 'application/octet-stream']);
    }

    /**
     * Point attachment links at the guest download route (the regular one needs a login).
     *
     * @param  array<int|string, mixed>  $messages
     * @return array<int|string, mixed>
     */
    private function withGuestAttachmentUrls(Ticket $ticket, array $messages): array
    {
        return array_map(function (mixed $message) use ($ticket): mixed {
            if (! is_array($message) || ! is_iterable($message['attachments'] ?? null)) {
                return $message;
            }

            $message['attachments'] = collect($message['attachments'])
                ->map(fn (array $attachment): array => [
                    ...$attachment,
                    'url' => route('guest.tickets.attachments.show', [$ticket, $attachment['id']]),
                ])
                ->values()
                ->all();

            return $message;
        }, $messages);
    }
}
