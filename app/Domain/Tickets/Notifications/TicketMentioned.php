<?php

namespace App\Domain\Tickets\Notifications;

use App\Domain\Support\RichText;
use App\Domain\Tickets\Models\TicketMessage;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to an agent who was @mentioned in an internal note.
 */
class TicketMentioned extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public TicketMessage $message) {}

    /**
     * @return list<string>
     */
    public function via(User $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $ticket = $this->message->ticket;

        return (new MailMessage)
            ->subject(__('[:number] You were mentioned: :subject', ['number' => $ticket->reference(), 'subject' => $ticket->subject]))
            ->greeting(__('Hi :name,', ['name' => $notifiable->name]))
            ->line(__(':name mentioned you in a note on ticket :number:', ['name' => $this->message->author->name ?? __('Someone'), 'number' => $ticket->reference()]))
            ->line(RichText::excerpt($this->message->body, 600))
            ->action(__('Open ticket'), route('agent.tickets.show', $ticket));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(User $notifiable): array
    {
        return [
            'kind' => 'ticket_mentioned',
            'ticket_id' => $this->message->ticket_id,
            'ticket_number' => $this->message->ticket->reference(),
            'subject' => $this->message->ticket->subject,
            'author' => $this->message->author?->name,
            'excerpt' => RichText::excerpt($this->message->body),
        ];
    }
}
