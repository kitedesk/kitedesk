<?php

namespace App\Console\Commands;

use App\Domain\Mail\Enums\MailboxDriver;
use App\Domain\Mail\Jobs\PollMailbox;
use App\Domain\Mail\Models\Mailbox;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('mail:poll {--mailbox= : Only poll the mailbox with this id}')]
#[Description('Fetch unread email from IMAP mailboxes and turn it into tickets and replies')]
class PollMailboxes extends Command
{
    public function handle(): int
    {
        $mailboxes = Mailbox::query()
            ->active()
            ->where('driver', MailboxDriver::Imap)
            ->when($this->option('mailbox'), fn ($query, $id) => $query->whereKey($id))
            ->get();

        foreach ($mailboxes as $mailbox) {
            PollMailbox::dispatch($mailbox);
        }

        $this->components->info("Queued {$mailboxes->count()} mailbox(es) for polling.");

        return self::SUCCESS;
    }
}
