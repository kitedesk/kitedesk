<?php

namespace App\Domain\Mail\Contracts;

use App\Domain\Mail\Imap\WebklexMailboxClient;
use App\Domain\Mail\Models\Mailbox;
use App\Domain\Mail\Support\InboundEmail;
use Closure;
use Illuminate\Container\Attributes\Bind;
use RuntimeException;

/**
 * Reads mail from a mailbox server. Faked in tests so they never talk to a real server.
 */
#[Bind(WebklexMailboxClient::class)]
interface MailboxClient
{
    /**
     * Hand each unread email to $handle, then mark it read (or delete it, per the mailbox setting).
     *
     * @param  Closure(InboundEmail): void  $handle
     * @return int How many emails were handled.
     */
    public function fetch(Mailbox $mailbox, Closure $handle, int $limit = 25): int;

    /**
     * Connect and open the folder.
     *
     * @throws RuntimeException When the connection or the folder fails, with a readable message.
     */
    public function test(Mailbox $mailbox): void;
}
