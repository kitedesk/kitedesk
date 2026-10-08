<?php

namespace App\Mail;

use App\Domain\Branding\Branding;
use App\Domain\Mail\Enums\EmailTemplateEvent;
use App\Domain\Mail\Models\EmailTemplate;
use App\Domain\Mail\Models\Mailbox;
use App\Domain\Mail\Support\MessageIds;
use App\Domain\Mail\Support\ReplyParser;
use App\Domain\Mail\Support\TemplateRenderer;
use App\Domain\Tickets\Models\Ticket;
use App\Domain\Tickets\Models\TicketMessage;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

/**
 * Every ticket email: replies, auto-replies, solved notices and agent alerts.
 *
 * It is sent from the ticket's mailbox, and carries Message-ID / In-Reply-To / References
 * headers so mail clients thread the conversation and replies find their way back.
 */
class TicketEmail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Ticket $ticket,
        public EmailTemplateEvent $event,
        public User $recipient,
        public ?TicketMessage $ticketMessage = null,
    ) {}

    public function envelope(): Envelope
    {
        $mailbox = Mailbox::forTicket($this->ticket);
        $from = $mailbox !== null
            ? new Address($mailbox->address, $mailbox->name !== '' ? $mailbox->name : Branding::current()->senderName())
            : new Address((string) config('mail.from.address'), Branding::current()->senderName());

        return new Envelope(
            from: $from,
            replyTo: [$from],
            subject: TemplateRenderer::text($this->template()->subject, $this->variables()),
        );
    }

    public function headers(): Headers
    {
        $references = MessageIds::thread($this->ticket, $this->ticketMessage);
        $text = ['In-Reply-To' => '<'.end($references).'>'];

        if (in_array($this->event, [EmailTemplateEvent::TicketReceived, EmailTemplateEvent::TicketSolved], true)) {
            // Tells the customer's mail server not to auto-reply to us (avoids mail loops).
            $text['Auto-Submitted'] = 'auto-replied';
        }

        return new Headers(
            messageId: $this->ticketMessage !== null
                ? MessageIds::forMessage($this->ticketMessage)
                : MessageIds::unique($this->ticket, str_replace('_', '-', $this->event->value)),
            references: $references,
            text: $text,
        );
    }

    public function content(): Content
    {
        $variables = $this->variables();

        return new Content(
            view: 'mail.ticket',
            with: [
                'marker' => $this->event->isForStaff() ? null : __(ReplyParser::MARKER),
                'intro' => TemplateRenderer::html($this->template()->body, $variables),
                'reply' => $this->ticketMessage,
                'history' => $this->history(),
                'url' => $variables['ticket.url'],
                'buttonLabel' => $this->recipient->isStaff() ? __('Open ticket') : __('View request'),
                'reference' => __('Request :number', ['number' => $this->ticket->reference()]),
                'timezone' => $this->recipient->timezone ?? config('app.timezone'),
            ],
        );
    }

    /**
     * Earlier public messages, newest first, quoted under the reply.
     *
     * @return Collection<int, TicketMessage>
     */
    private function history(): Collection
    {
        if ($this->event !== EmailTemplateEvent::AgentReply) {
            return collect();
        }

        return $this->ticket->messages()
            ->public()
            ->with('author')
            ->when($this->ticketMessage !== null, fn ($query) => $query->where('id', '<', $this->ticketMessage?->id))
            ->latest('id')
            ->limit(3)
            ->get();
    }

    private function template(): EmailTemplate
    {
        return EmailTemplate::for($this->event);
    }

    /**
     * @return array<string, string>
     */
    private function variables(): array
    {
        return TemplateRenderer::variables($this->ticket, $this->recipient, $this->ticketMessage?->author);
    }
}
