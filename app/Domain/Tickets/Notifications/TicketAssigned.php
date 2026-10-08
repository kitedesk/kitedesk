<?php

namespace App\Domain\Tickets\Notifications;

use App\Domain\Tickets\Models\Ticket;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TicketAssigned extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Ticket $ticket, public ?User $assignedBy = null) {}

    /**
     * @return list<string>
     */
    public function via(User $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('[:number] Assigned to you: :subject', ['number' => $this->ticket->reference(), 'subject' => $this->ticket->subject]))
            ->line(__(':name assigned ticket :number to you.', ['name' => $this->assignedBy->name ?? __('The system'), 'number' => $this->ticket->reference()]))
            ->action(__('Open ticket'), route('agent.tickets.show', $this->ticket));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(User $notifiable): array
    {
        return [
            'kind' => 'ticket_assigned',
            'ticket_id' => $this->ticket->id,
            'ticket_number' => $this->ticket->reference(),
            'subject' => $this->ticket->subject,
            'author' => $this->assignedBy?->name,
        ];
    }
}
