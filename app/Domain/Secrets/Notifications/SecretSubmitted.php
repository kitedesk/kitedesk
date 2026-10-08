<?php

namespace App\Domain\Secrets\Notifications;

use App\Domain\Secrets\Models\Secret;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells the agent who requested a secret that the customer sent it. It never carries the
 * secret itself, which is only shown in the ticket to signed-in agents.
 */
class SecretSubmitted extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Secret $secret) {}

    /**
     * @return list<string>
     */
    public function via(User $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $ticket = $this->secret->ticket;

        return (new MailMessage)
            ->subject(__('[:number] Secret received: :label', ['number' => $ticket->reference(), 'label' => $this->secret->label]))
            ->greeting(__('Hi :name,', ['name' => $notifiable->name]))
            ->line(__(':name sent the secret you requested (:label) on ticket :number. Open the ticket to read it.', ['name' => $this->secret->submitter->name ?? __('The customer'), 'label' => $this->secret->label, 'number' => $ticket->reference()]))
            ->action(__('Open ticket'), route('agent.tickets.show', $ticket));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(User $notifiable): array
    {
        return [
            'kind' => 'secret_submitted',
            'ticket_id' => $this->secret->ticket_id,
            'ticket_number' => $this->secret->ticket->reference(),
            'subject' => $this->secret->ticket->subject,
            'message' => $this->secret->label,
        ];
    }
}
