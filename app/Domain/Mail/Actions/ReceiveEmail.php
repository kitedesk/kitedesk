<?php

namespace App\Domain\Mail\Actions;

use App\Domain\Accounts\Enums\Permission;
use App\Domain\Mail\Enums\SenderVerification;
use App\Domain\Mail\Models\Mailbox;
use App\Domain\Mail\Support\InboundAttachment;
use App\Domain\Mail\Support\InboundEmail;
use App\Domain\Mail\Support\MessageIds;
use App\Domain\Mail\Support\ReplyParser;
use App\Domain\Support\RichText;
use App\Domain\Tickets\Actions\AddMessage;
use App\Domain\Tickets\Actions\CreateTicket;
use App\Domain\Tickets\Enums\TicketChannel;
use App\Domain\Tickets\Enums\TicketStatus;
use App\Domain\Tickets\Models\Ticket;
use App\Domain\Tickets\Models\TicketMessage;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Turns an incoming email into a new ticket or a reply on an existing one.
 */
class ReceiveEmail
{
    /**
     * Larger attachments are skipped (same limit as uploads in the app).
     */
    private const int MAX_ATTACHMENT_BYTES = 20 * 1024 * 1024;

    /**
     * At most this many attachments, and this many bytes in all, are kept from one email.
     */
    private const int MAX_ATTACHMENTS = 20;

    private const int MAX_TOTAL_ATTACHMENT_BYTES = 50 * 1024 * 1024;

    /**
     * Temporary copies handed to the media library, removed whatever happens.
     *
     * @var list<string>
     */
    private array $temporaryFiles = [];

    /**
     * "[#42]" or "[TKT-00042]" in a subject.
     */
    private const string SUBJECT_TOKEN = '/\[(#?[A-Za-z0-9][A-Za-z0-9\-_.\/]{0,39})\]\s*/';

    public function __construct(private CreateTicket $createTicket, private AddMessage $addMessage) {}

    /**
     * @return TicketMessage|null The stored message, or null when the email was ignored.
     */
    public function handle(Mailbox $mailbox, InboundEmail $email): ?TicketMessage
    {
        if (($reason = $this->ignoreReason($email)) !== null) {
            Log::info('Inbound email ignored', ['mailbox' => $mailbox->address, 'from' => $email->fromEmail, 'reason' => $reason]);

            return null;
        }

        if ($email->messageId !== null && TicketMessage::query()->where('email_message_id', $email->messageId)->exists()) {
            return null;
        }

        try {
            return DB::transaction(function () use ($mailbox, $email): TicketMessage {
                $sender = User::query()->firstOrCreate(
                    ['email' => Str::lower($email->fromEmail)],
                    ['name' => ReplyParser::nameFor($email->fromEmail, $email->fromName), 'password' => Str::random(40)],
                );
                $verification = $email->senderVerification();

                $referenced = $this->threadedTicket($email) ?? $this->ticketFromSubject($email);
                $ticket = $referenced !== null && $this->mayReplyTo($referenced, $sender, $verification) ? $this->followMerges($referenced) : null;

                $message = match (true) {
                    $ticket === null => $this->openTicket($mailbox, $email, $sender, $verification, relatedTo: $referenced),
                    $ticket->status === TicketStatus::Closed => $this->openTicket($mailbox, $email, $sender, $verification, followUpOf: $ticket),
                    default => $this->reply($ticket, $email, $sender, $verification),
                };

                $message->forceFill([
                    'email_message_id' => $email->messageId,
                    'metadata' => [
                        ...($message->metadata ?? []),
                        'from' => $email->fromEmail,
                        'mailbox_id' => $mailbox->id,
                        'message_id' => $email->messageId,
                        'in_reply_to' => $email->inReplyTo,
                        'sender_verification' => $verification->value,
                    ],
                ])->save();

                return $message;
            });
        } catch (UniqueConstraintViolationException) {
            // The same email arrived twice at the same time; the first copy won.
            return null;
        } finally {
            foreach ($this->temporaryFiles as $path) {
                if (is_file($path)) {
                    unlink($path);
                }
            }

            $this->temporaryFiles = [];
        }
    }

    /**
     * Why the email must not reach a ticket, if it mustn't.
     */
    private function ignoreReason(InboundEmail $email): ?string
    {
        $from = Str::lower($email->fromEmail);

        return match (true) {
            ! filter_var($from, FILTER_VALIDATE_EMAIL) => 'invalid sender',
            $email->isAutomated() => 'automated message',
            Mailbox::owns($from) => 'sent from one of our mailboxes',
            $this->isBanned($from) => 'banned sender',
            $this->isDeactivated($from) => 'deactivated sender',
            default => null,
        };
    }

    private function isDeactivated(string $address): bool
    {
        return User::query()->where('email', $address)->whereNotNull('deactivated_at')->exists();
    }

    private function isBanned(string $from): bool
    {
        return collect((array) config('kitedesk.mail.banlist', []))
            ->map(fn (mixed $entry): string => Str::lower(trim((string) $entry)))
            ->filter()
            ->contains(fn (string $entry): bool => str_starts_with($entry, '@') ? str_ends_with($from, $entry) : $from === $entry);
    }

    /**
     * The ticket whose email thread this replies to (In-Reply-To / References).
     */
    private function threadedTicket(InboundEmail $email): ?Ticket
    {
        $ids = $email->threadIds();

        if ($ids === []) {
            return null;
        }

        $stored = TicketMessage::query()->whereIn('email_message_id', $ids)->latest('id')->first();

        if ($stored !== null) {
            return $stored->ticket;
        }

        foreach ($ids as $id) {
            if (($ticketId = MessageIds::ticketIdFrom($id)) !== null && ($ticket = Ticket::query()->find($ticketId)) !== null) {
                return $ticket;
            }
        }

        return null;
    }

    /**
     * The ticket named by a "[#123]" or "[TKT-00042]" token in the subject.
     */
    private function ticketFromSubject(InboundEmail $email): ?Ticket
    {
        foreach (self::subjectTokens($email->subject) as $token) {
            if (($ticket = Ticket::findByReference($token)) !== null) {
                return $ticket;
            }
        }

        return null;
    }

    /**
     * Only staff and people already on the ticket may add to it by email, and never with a
     * From address their domain disowns: thread headers and subject tokens are easy to copy.
     */
    private function mayReplyTo(Ticket $ticket, User $sender, SenderVerification $verification): bool
    {
        return $verification !== SenderVerification::Failed && ($sender->isStaff() || $ticket->involves($sender));
    }

    /**
     * Bracketed tokens that may be ticket references.
     *
     * @return list<string>
     */
    private static function subjectTokens(string $subject): array
    {
        preg_match_all(self::SUBJECT_TOKEN, $subject, $matches);

        return $matches[1];
    }

    /**
     * @param  Ticket|null  $followUpOf  The closed ticket this email replied to.
     * @param  Ticket|null  $relatedTo  A ticket the email pointed at but the sender may not join (agents can merge them).
     */
    private function openTicket(Mailbox $mailbox, InboundEmail $email, User $sender, SenderVerification $verification, ?Ticket $followUpOf = null, ?Ticket $relatedTo = null): TicketMessage
    {
        $ticket = $this->createTicket->handle($sender, [
            'subject' => $this->subject($email, $followUpOf),
            'body' => $this->body($email),
            'group_id' => $mailbox->default_group_id,
            'category_id' => $followUpOf->category_id ?? $mailbox->default_category_id,
            'collaborator_ids' => $verification === SenderVerification::Failed ? [] : $this->copiedPeople($email, $sender),
            'tags' => array_values(array_filter([
                $followUpOf !== null ? 'follow_up' : null,
                $verification === SenderVerification::Failed ? 'unverified_sender' : null,
            ])),
            'attachments' => $this->attachments($email),
        ], TicketChannel::Email);

        $ticket->forceFill(['mailbox_id' => $mailbox->id])->save();

        $message = $ticket->messages()->oldest('id')->firstOrFail();

        if ($followUpOf !== null) {
            $message->metadata = ['follow_up_of' => $followUpOf->id];
        }

        if (($linked = $followUpOf ?? $relatedTo) !== null) {
            $ticket->linkTo($linked);
        }

        return $message;
    }

    /**
     * Replies to a merged duplicate belong on the ticket it was merged into.
     */
    private function followMerges(?Ticket $ticket): ?Ticket
    {
        $seen = [];

        while ($ticket?->merged_into_id !== null && ! in_array($ticket->id, $seen, true)) {
            $seen[] = $ticket->id;
            $ticket = $ticket->mergedInto ?? $ticket;
        }

        return $ticket;
    }

    /**
     * Staff mail goes out to the customer only when the sender's domain vouched for it;
     * otherwise it is kept as an internal note for the agent to check.
     */
    private function reply(Ticket $ticket, InboundEmail $email, User $sender, SenderVerification $verification): TicketMessage
    {
        return $this->addMessage->handle(
            $ticket,
            $sender,
            $this->body($email),
            isInternal: $sender->isStaff() && (! $sender->hasPermission(Permission::ReplyToTickets) || $verification !== SenderVerification::Passed),
            channel: TicketChannel::Email,
            attachments: $this->attachments($email),
        );
    }

    private function subject(InboundEmail $email, ?Ticket $followUpOf): string
    {
        $subject = trim((string) preg_replace('/^\s*((re|fw|fwd|res|enc)\s*:\s*)+/i', '', $email->subject));
        $subject = trim((string) preg_replace_callback(
            self::SUBJECT_TOKEN.'u',
            fn (array $match): string => Ticket::findByReference($match[1]) !== null ? '' : $match[0],
            $subject,
        ));

        return Str::limit($subject !== '' ? $subject : ($followUpOf->subject ?? __('(no subject)')), 250, '');
    }

    private function body(InboundEmail $email): string
    {
        $body = RichText::sanitizeInboundEmail(ReplyParser::body($email));

        return $body !== '' ? $body : '<p>'.e(__('(empty message)')).'</p>';
    }

    /**
     * Other people the customer wrote to or copied (up to the configured limit), except our own
     * mailboxes, banned addresses and deactivated people.
     *
     * @return list<int>
     */
    private function copiedPeople(InboundEmail $email, User $sender): array
    {
        $ourAddresses = Mailbox::query()->pluck('address')->map(fn (string $address): string => Str::lower($address))->all();

        $ids = collect($email->recipientAddresses())
            ->reject(fn (string $address): bool => in_array($address, $ourAddresses, true) || $address === Str::lower($sender->email))
            ->filter(fn (string $address): bool => filter_var($address, FILTER_VALIDATE_EMAIL) !== false && ! $this->isBanned($address) && ! $this->isDeactivated($address))
            ->take(max(0, (int) config('kitedesk.mail.max_copied_people', 10)))
            ->map(function (string $address) use ($email): int {
                $name = collect([...$email->to, ...$email->cc])->firstWhere('email', $address)['name'] ?? '';

                return User::query()->firstOrCreate(
                    ['email' => $address],
                    ['name' => ReplyParser::nameFor($address, (string) $name), 'password' => Str::random(40)],
                )->id;
            })
            ->values()
            ->all();

        return array_values($ids);
    }

    /**
     * @return list<UploadedFile>
     */
    private function attachments(InboundEmail $email): array
    {
        $files = [];
        $totalBytes = 0;

        foreach ($email->attachments as $attachment) {
            if ($attachment->size() > self::MAX_ATTACHMENT_BYTES || $totalBytes + $attachment->size() > self::MAX_TOTAL_ATTACHMENT_BYTES) {
                continue;
            }

            if (count($files) >= self::MAX_ATTACHMENTS) {
                break;
            }

            $files[] = $this->toUploadedFile($attachment);
            $totalBytes += $attachment->size();
        }

        return $files;
    }

    private function toUploadedFile(InboundAttachment $attachment): UploadedFile
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'kd-mail-');
        file_put_contents($path, $attachment->contents());
        $this->temporaryFiles[] = $path;

        $name = Str::limit(basename(str_replace('\\', '/', $attachment->name)) ?: 'attachment', 200, '');

        return new UploadedFile($path, $name, $attachment->mimeType, null, true);
    }
}
