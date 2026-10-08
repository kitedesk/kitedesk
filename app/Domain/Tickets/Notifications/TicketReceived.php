<?php

namespace App\Domain\Tickets\Notifications;

use App\Domain\Tickets\Models\Ticket;
use App\Domain\Tickets\Support\GuestAccess;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Confirms a request submitted from the public form, with a link to follow it.
 */
class TicketReceived extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Ticket $ticket) {}

    /**
     * @return list<string>
     */
    public function via(User $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("[{$this->ticket->reference()}] {$this->ticket->subject}")
            ->greeting(__('Hi :name,', ['name' => $notifiable->name]))
            ->line(__('We received your request :number and will get back to you soon.', ['number' => $this->ticket->reference()]))
            ->action(__('View your request'), GuestAccess::urlFor($this->ticket, $notifiable))
            ->line(__('Keep this email: the link lets you follow the conversation and reply.'));
    }
}
