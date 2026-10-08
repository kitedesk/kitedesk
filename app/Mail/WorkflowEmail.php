<?php

namespace App\Mail;

use App\Domain\Branding\Branding;
use App\Domain\Mail\Models\Mailbox;
use App\Domain\Mail\Support\MessageIds;
use App\Domain\Tickets\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;

/**
 * A free-form email sent by a workflow's "Send email" action, from the ticket's mailbox.
 */
class WorkflowEmail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Ticket $ticket,
        public string $emailSubject,
        public string $body,
        public ?string $url = null,
        public bool $forStaff = false,
    ) {}

    public function envelope(): Envelope
    {
        $mailbox = Mailbox::forTicket($this->ticket);
        $from = $mailbox !== null
            ? new Address($mailbox->address, $mailbox->name !== '' ? $mailbox->name : Branding::current()->senderName())
            : new Address((string) config('mail.from.address'), Branding::current()->senderName());

        return new Envelope(from: $from, replyTo: [$from], subject: $this->emailSubject);
    }

    public function headers(): Headers
    {
        return new Headers(
            messageId: MessageIds::unique($this->ticket, 'workflow'),
            text: ['Auto-Submitted' => 'auto-generated'],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.ticket',
            with: [
                'marker' => null,
                'intro' => $this->body,
                'reply' => null,
                'history' => collect(),
                'url' => $this->url,
                'buttonLabel' => $this->forStaff ? __('Open ticket') : __('View request'),
                'reference' => __('Request :number', ['number' => $this->ticket->reference()]),
                'timezone' => config('app.timezone'),
            ],
        );
    }
}
