<?php

namespace App\Domain\Mail\Jobs;

use App\Domain\Mail\Contracts\MailboxClient;
use App\Domain\Mail\Models\Mailbox;
use App\Domain\Mail\Support\InboundEmail;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Fetches one IMAP mailbox. Each mailbox polls in its own job, so a slow or unreachable
 * server never holds up the others, and a mailbox is never polled twice at once.
 */
class PollMailbox implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 300;

    /**
     * The unique lock expires on its own if a worker dies mid-poll.
     */
    public int $uniqueFor = 600;

    public function __construct(public Mailbox $mailbox) {}

    public function uniqueId(): string
    {
        return (string) $this->mailbox->id;
    }

    public function handle(MailboxClient $client): void
    {
        $mailbox = $this->mailbox;

        try {
            $client->fetch($mailbox, function (InboundEmail $email) use ($mailbox): void {
                ProcessInboundEmail::dispatch($mailbox, $email);
            });

            $mailbox->forceFill(['last_polled_at' => now(), 'last_error' => null])->save();
        } catch (Throwable $exception) {
            report($exception);
            $mailbox->forceFill(['last_polled_at' => now(), 'last_error' => $exception->getMessage()])->save();
        }
    }
}
