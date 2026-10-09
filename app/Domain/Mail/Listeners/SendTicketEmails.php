<?php

namespace App\Domain\Mail\Listeners;

use App\Domain\Mail\Enums\EmailTemplateEvent;
use App\Domain\Mail\Models\EmailTemplate;
use App\Domain\Tickets\Enums\TicketChannel;
use App\Domain\Tickets\Enums\TicketStatus;
use App\Domain\Tickets\Events\TicketCreated;
use App\Domain\Tickets\Events\TicketUpdated;
use App\Domain\Tickets\Models\Ticket;
use App\Mail\TicketEmail;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;

/**
 * Template emails: the auto-reply and agent alert for new tickets, and the "solved" notice.
 * (Conversation replies go out through the TicketReplied notification.)
 */
class SendTicketEmails implements ShouldQueue
{
    /**
     * A requester who opened another ticket this recently doesn't get another auto-reply.
     */
    private const int AUTO_REPLY_QUIET_MINUTES = 10;

    public function handle(TicketCreated|TicketUpdated $event): void
    {
        $event instanceof TicketCreated
            ? $this->ticketCreated($event->ticket)
            : $this->ticketUpdated($event);
    }

    private function ticketCreated(Ticket $ticket): void
    {
        if ($this->isActive(EmailTemplateEvent::TicketReceived) && ! in_array($ticket->channel, [TicketChannel::Agent, TicketChannel::Internal], true) && ! $this->recentlyAutoReplied($ticket)) {
            $this->send($ticket, EmailTemplateEvent::TicketReceived, collect([$ticket->requester]));
        }

        // Tickets agents log themselves don't need to alert the team; internal requests from another department do.
        if ($this->isActive(EmailTemplateEvent::AgentNewTicketAlert) && $ticket->channel !== TicketChannel::Agent) {
            $this->send($ticket, EmailTemplateEvent::AgentNewTicketAlert, $this->agentsFor($ticket));
        }
    }

    private function ticketUpdated(TicketUpdated $event): void
    {
        if (($event->changes['status']['to'] ?? null) !== TicketStatus::Solved->value || ! $this->isActive(EmailTemplateEvent::TicketSolved)) {
            return;
        }

        $ticket = $event->ticket;

        $this->send($ticket, EmailTemplateEvent::TicketSolved, collect([$ticket->requester])->merge($ticket->collaborators));
    }

    /**
     * @param  Collection<int, User>  $recipients
     */
    private function send(Ticket $ticket, EmailTemplateEvent $event, Collection $recipients): void
    {
        $recipients
            ->unique('id')
            ->each(fn (User $recipient) => Mail::to($recipient->email, $recipient->name)
                ->queue(new TicketEmail($ticket, $event, $recipient)));
    }

    private function isActive(EmailTemplateEvent $event): bool
    {
        return EmailTemplate::for($event)->is_active;
    }

    private function recentlyAutoReplied(Ticket $ticket): bool
    {
        return Ticket::query()
            ->where('requester_id', $ticket->requester_id)
            ->whereKeyNot($ticket->id)
            ->where('created_at', '>=', now()->subMinutes(self::AUTO_REPLY_QUIET_MINUTES))
            ->exists();
    }

    /**
     * Agents of the ticket's group, or everyone who can be assigned tickets when it has no group.
     *
     * @return Collection<int, User>
     */
    private function agentsFor(Ticket $ticket): Collection
    {
        $agents = $ticket->group !== null
            ? $ticket->group->agents()->assignable()->get()
            : User::query()->assignable()->get();

        // An agent asking their own department doesn't need to hear about their request.
        return $agents->reject(fn (User $agent): bool => $agent->id === $ticket->requester_id);
    }
}
