<?php

namespace App\Domain\Tickets\Actions;

use App\Domain\Mail\Enums\EmailTemplateEvent;
use App\Domain\Mail\Models\EmailTemplate;
use App\Domain\Tickets\Enums\TicketChannel;
use App\Domain\Tickets\Notifications\TicketReceived;
use App\Domain\Tickets\Support\GuestSubmission;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

/**
 * Opens a ticket for someone who isn't signed in (the guest form and the website widget),
 * finding or creating the requester by email. The requester always gets the link to the
 * ticket by email.
 */
class SubmitGuestTicket
{
    public function __construct(private CreateTicket $createTicket) {}

    /**
     * @param  array{
     *     name: string,
     *     email: string,
     *     subject: string,
     *     body: string,
     *     category_id?: int|null,
     *     custom_fields?: array<string, mixed>,
     *     attachments?: list<UploadedFile>,
     * }  $data
     * @return GuestSubmission|null null when the email belongs to a deactivated person, who can't open requests
     */
    public function handle(array $data, TicketChannel $channel): ?GuestSubmission
    {
        $email = Str::lower($data['email']);
        $requester = User::query()->where('email', $email)->first();
        $isNewPerson = $requester === null;

        if ($requester?->isDeactivated() === true) {
            return null;
        }

        $requester ??= User::query()->create([
            'name' => $data['name'],
            'email' => $email,
            'password' => Str::random(40),
        ]);

        $ticket = $this->createTicket->handle($requester, [
            'subject' => $data['subject'],
            'body' => $data['body'],
            'category_id' => $data['category_id'] ?? null,
            'custom_fields' => $data['custom_fields'] ?? [],
            'attachments' => $data['attachments'] ?? [],
            // Anyone can type an existing account's email here; agents see the request wasn't signed in.
            'tags' => $isNewPerson ? [] : ['unverified_sender'],
        ], $channel);

        // The "request received" auto-reply already carries the link; send it ourselves only when it's off.
        if (! EmailTemplate::for(EmailTemplateEvent::TicketReceived)->is_active) {
            $requester->notify(new TicketReceived($ticket));
        }

        return new GuestSubmission($ticket, $requester, $isNewPerson);
    }
}
