<?php

namespace App\Mail;

use App\Domain\Branding\Branding;
use App\Domain\Mail\Models\Mailbox;
use App\Domain\Mail\Support\MessageIds;
use App\Domain\Tickets\Models\SatisfactionRating;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;

/**
 * Asks the requester to rate how their solved ticket was handled. Each star is a signed link
 * to the rating page, so answering needs no login.
 */
class SatisfactionSurveyEmail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public SatisfactionRating $rating) {}

    public function envelope(): Envelope
    {
        $ticket = $this->rating->ticket;
        $mailbox = Mailbox::forTicket($ticket);
        $from = $mailbox !== null
            ? new Address($mailbox->address, $mailbox->name !== '' ? $mailbox->name : Branding::current()->senderName())
            : new Address((string) config('mail.from.address'), Branding::current()->senderName());

        return new Envelope(
            from: $from,
            subject: __('[:number] How did we do? :subject', ['number' => $ticket->reference(), 'subject' => $ticket->subject]),
        );
    }

    public function headers(): Headers
    {
        $references = MessageIds::thread($this->rating->ticket);

        return new Headers(
            messageId: MessageIds::unique($this->rating->ticket, 'satisfaction'),
            references: $references,
            // Tells the customer's mail server not to auto-reply to us (avoids mail loops).
            text: ['In-Reply-To' => '<'.end($references).'>', 'Auto-Submitted' => 'auto-generated'],
        );
    }

    public function content(): Content
    {
        $ticket = $this->rating->ticket;

        return new Content(
            view: 'mail.satisfaction',
            with: [
                'name' => ($this->rating->user ?? $ticket->requester)->name,
                'subject' => $ticket->subject,
                'reference' => __('Request :number', ['number' => $ticket->reference()]),
                'stars' => collect(range(1, 5))->map(fn (int $score): array => [
                    'score' => $score,
                    'url' => $this->rating->urlFor($score),
                ])->all(),
            ],
        );
    }
}
