<?php

namespace App\Domain\Sla\Notifications;

use App\Domain\Tickets\Models\Ticket;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SlaBreached extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Ticket $ticket) {}

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
            ->error()
            ->subject(__('[:number] SLA breached: :subject', ['number' => $this->ticket->reference(), 'subject' => $this->ticket->subject]))
            ->line(__('Ticket :number has missed its service level target.', ['number' => $this->ticket->reference()]))
            ->action(__('Open ticket'), route('agent.tickets.show', $this->ticket));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(User $notifiable): array
    {
        return [
            'kind' => 'sla_breached',
            'ticket_id' => $this->ticket->id,
            'ticket_number' => $this->ticket->reference(),
            'subject' => $this->ticket->subject,
        ];
    }
}
