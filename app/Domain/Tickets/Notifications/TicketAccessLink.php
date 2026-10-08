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
 * A fresh link to a request, sent from the "Check a request" page.
 */
class TicketAccessLink extends Notification implements ShouldQueue
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
            ->subject(__('Your link to request :number', ['number' => $this->ticket->reference()]))
            ->greeting(__('Hi :name,', ['name' => $notifiable->name]))
            ->line(__('Use the button below to see request :number: :subject.', ['number' => $this->ticket->reference(), 'subject' => $this->ticket->subject]))
            ->action(__('View your request'), GuestAccess::urlFor($this->ticket, $notifiable))
            ->line(__("If you didn't ask for this link, you can ignore this email."));
    }
}
