<?php

namespace App\Http\Controllers\Portal;

use App\Domain\Tickets\Actions\AddMessage;
use App\Domain\Tickets\Actions\SubmitGuestTicket;
use App\Domain\Tickets\Enums\TicketChannel;
use App\Domain\Tickets\Enums\TicketStatus;
use App\Domain\Tickets\Models\Ticket;
use App\Domain\Tickets\Models\TicketMessage;
use App\Domain\Tickets\Support\GuestAccess;
use App\Domain\Tickets\Support\SatisfactionSurvey;
use App\Domain\Tickets\Support\TicketCatalog;
use App\Domain\Tickets\Support\TicketThread;
use App\Http\Controllers\Controller;
use App\Http\Requests\Portal\StoreGuestReplyRequest;
use App\Http\Requests\Portal\StoreGuestTicketRequest;
use App\Http\Resources\TicketMessageResource;
use App\Http\Resources\TicketResource;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

    public function store(StoreGuestTicketRequest $request, SubmitGuestTicket $submitGuestTicket): RedirectResponse
    {
        abort_unless(GuestAccess::enabled(), 404);

        $submission = $submitGuestTicket->handle([
            'name' => $request->string('name')->toString(),
            'email' => $request->string('email')->toString(),
            'subject' => $request->string('subject')->toString(),
            'body' => $request->string('body')->toString(),
            'category_id' => $request->validated('category_id'),
            'custom_fields' => $request->customFields(),
            'attachments' => $request->attachments(),
        ], TicketChannel::Portal);

        // Deactivated people can't open requests; answer as for any existing account.
        if ($submission === null) {
            Inertia::flash('toast', ['type' => 'success', 'message' => __('Thanks! We emailed you a link to follow your request.')]);

            return to_route('guest.check');
        }

        $ticket = $submission->ticket;

        // Someone typing an existing account's email must not see that account's tickets:
        // they get the link by email instead.
        if (! $submission->isNewPerson) {
            Inertia::flash('toast', ['type' => 'success', 'message' => __('Thanks! We emailed you a link to follow request :number.', ['number' => $ticket->reference()])]);

            return to_route('guest.check');
        }

        GuestAccess::grant($request, $ticket, $submission->requester);
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
