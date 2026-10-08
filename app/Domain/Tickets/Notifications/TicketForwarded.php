<?php

namespace App\Domain\Tickets\Notifications;

use App\Domain\Support\RichText;
use App\Domain\Tickets\Models\Ticket;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to the agent a ticket was forwarded to, or to every member of the group it was
 * forwarded to when nobody was assigned.
 */
class TicketForwarded extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  string|null  $group  Name of the group it was forwarded to, null when forwarded to an agent.
     * @param  string  $note  The handover note (HTML), possibly empty.
     */
    public function __construct(public Ticket $ticket, public User $forwardedBy, public ?string $group = null, public string $note = '') {}

    /**
     * @return list<string>
     */
    public function via(User $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $number = $this->ticket->reference();
        $excerpt = RichText::excerpt($this->note, 600);

        return (new MailMessage)
            ->subject($this->group === null
                ? __('[:number] Forwarded to you: :subject', ['number' => $number, 'subject' => $this->ticket->subject])
                : __('[:number] Forwarded to :group: :subject', ['number' => $number, 'group' => $this->group, 'subject' => $this->ticket->subject]))
            ->greeting(__('Hi :name,', ['name' => $notifiable->name]))
            ->line($this->group === null
                ? __(':name forwarded ticket :number to you.', ['name' => $this->forwardedBy->name, 'number' => $number])
                : __(':name forwarded ticket :number to :group.', ['name' => $this->forwardedBy->name, 'number' => $number, 'group' => $this->group]))
            ->when($excerpt !== '', fn (MailMessage $mail) => $mail->line($excerpt))
            ->action(__('Open ticket'), route('agent.tickets.show', $this->ticket));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(User $notifiable): array
    {
        return [
            'kind' => 'ticket_forwarded',
            'ticket_id' => $this->ticket->id,
            'ticket_number' => $this->ticket->reference(),
            'subject' => $this->ticket->subject,
            'author' => $this->forwardedBy->name,
            'group' => $this->group,
            'excerpt' => RichText::excerpt($this->note),
        ];
    }
}
