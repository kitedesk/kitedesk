<?php

namespace App\Domain\Workflows\Notifications;

use App\Domain\Tickets\Models\Ticket;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent by a workflow's "Notify agents" action.
 */
class WorkflowAlert extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Ticket $ticket, public string $workflowName, public string $message) {}

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
            ->subject(__('[:number] :subject', ['number' => $this->ticket->reference(), 'subject' => $this->ticket->subject]))
            ->line($this->message)
            ->action(__('Open ticket'), route('agent.tickets.show', $this->ticket))
            ->line(__('Sent by the workflow ":name".', ['name' => $this->workflowName]));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(User $notifiable): array
    {
        return [
            'kind' => 'workflow_alert',
            'ticket_id' => $this->ticket->id,
            'ticket_number' => $this->ticket->reference(),
            'subject' => $this->ticket->subject,
            'message' => $this->message,
            'workflow' => $this->workflowName,
        ];
    }
}
