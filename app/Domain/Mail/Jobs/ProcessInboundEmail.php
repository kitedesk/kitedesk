<?php

namespace App\Domain\Mail\Jobs;

use App\Domain\Mail\Actions\ReceiveEmail;
use App\Domain\Mail\Models\Mailbox;
use App\Domain\Mail\Support\InboundEmail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Processes one received email off the request/poller path.
 */
class ProcessInboundEmail implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    public function __construct(public Mailbox $mailbox, public InboundEmail $email) {}

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [10, 60];
    }

    public function handle(ReceiveEmail $receiveEmail): void
    {
        $receiveEmail->handle($this->mailbox, $this->email);

        $this->deleteAttachments();
    }

    public function failed(): void
    {
        $this->deleteAttachments();
    }

    private function deleteAttachments(): void
    {
        foreach ($this->email->attachments as $attachment) {
            $attachment->delete();
        }
    }
}
