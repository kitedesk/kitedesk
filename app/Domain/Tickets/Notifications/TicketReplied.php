<?php

namespace App\Domain\Tickets\Notifications;

use App\Domain\Mail\Enums\EmailTemplateEvent;
use App\Domain\Mail\Models\EmailTemplate;
use App\Domain\Support\RichText;
use App\Domain\Tickets\Models\TicketMessage;
use App\Mail\TicketEmail;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to the requester when support replies, or to the assignee when the customer replies.
 */
class TicketReplied extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public TicketMessage $message) {}

    /**
     * @return list<string>
     */
    public function via(User $notifiable): array
    {
        if ($notifiable->isStaff()) {
            return ['mail', 'database'];
        }

        // Customers get the threaded conversation email, unless an admin switched it off.
        return EmailTemplate::for(EmailTemplateEvent::AgentReply)->is_active ? ['mail'] : [];
    }

    public function toMail(User $notifiable): MailMessage|TicketEmail
    {
        $ticket = $this->message->ticket;
        $author = $this->message->author;

        if (! $notifiable->isStaff()) {
            return (new TicketEmail($ticket, EmailTemplateEvent::AgentReply, $notifiable, $this->message))->to($notifiable->email, $notifiable->name);
        }

        // Staff alert; the subject keeps the [number] token so agents can answer by email too.
        return (new MailMessage)
            ->subject("[{$ticket->reference()}] Re: {$ticket->subject}")
            ->greeting(__('Hi :name,', ['name' => $notifiable->name]))
            ->line(__(':name replied to ticket :number:', ['name' => $author->name ?? __('Someone'), 'number' => $ticket->reference()]))
            ->line(RichText::excerpt($this->message->body, 600))
            ->action(__('View conversation'), route('agent.tickets.show', $ticket));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(User $notifiable): array
    {
        return [
            'kind' => 'ticket_replied',
            'ticket_id' => $this->message->ticket_id,
            'ticket_number' => $this->message->ticket->reference(),
            'subject' => $this->message->ticket->subject,
            'author' => $this->message->author?->name,
            'excerpt' => RichText::excerpt($this->message->body),
        ];
    }
}
