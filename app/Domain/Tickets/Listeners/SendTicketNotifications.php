<?php

namespace App\Domain\Tickets\Listeners;

use App\Domain\Support\Mentions;
use App\Domain\Tickets\Events\MessageCreated;
use App\Domain\Tickets\Events\TicketCreated;
use App\Domain\Tickets\Events\TicketUpdated;
use App\Domain\Tickets\Notifications\TicketAssigned;
use App\Domain\Tickets\Notifications\TicketMentioned;
use App\Domain\Tickets\Notifications\TicketReplied;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Runs on the queue so replies and updates return without waiting on notification work.
 */
class SendTicketNotifications implements ShouldQueue
{
    public function handle(TicketCreated|MessageCreated|TicketUpdated $event): void
    {
        match (true) {
            $event instanceof TicketCreated => $this->ticketCreated($event),
            $event instanceof MessageCreated => $this->messageCreated($event),
            default => $this->ticketUpdated($event),
        };
    }

    /**
     * Tickets that start out assigned (by the creating agent or auto-assignment) alert the assignee.
     */
    private function ticketCreated(TicketCreated $event): void
    {
        $ticket = $event->ticket;
        $creatorId = $ticket->messages()->oldest('id')->value('author_id');

        if ($ticket->assignee_id === null || $ticket->assignee_id === $creatorId) {
            return;
        }

        $ticket->assignee?->notify(new TicketAssigned($ticket));
    }

    private function messageCreated(MessageCreated $event): void
    {
        $message = $event->message;

        if ($message->is_internal) {
            $this->notifyMentioned($event);

            return;
        }

        $ticket = $message->ticket;
        $author = $message->author;

        // Everyone on the customer side hears about public replies; a customer-side reply also alerts the assignee.
        $recipients = collect([$ticket->requester])
            ->merge($ticket->collaborators)
            ->when($author?->isStaff() === false, fn ($recipients) => $recipients->push($ticket->assignee));

        $recipients
            ->filter()
            ->unique('id')
            ->reject(fn (User $recipient): bool => $recipient->id === $author?->id)
            ->each(fn (User $recipient) => $recipient->notify(new TicketReplied($message)));
    }

    /**
     * Agents @mentioned in an internal note; customers and the author are never notified.
     */
    private function notifyMentioned(MessageCreated $event): void
    {
        $message = $event->message;
        $ids = array_diff(Mentions::userIds($message->body), [$message->author_id]);

        if ($ids === []) {
            return;
        }

        User::query()->staff()->whereKey($ids)->get()
            ->each(fn (User $agent) => $agent->notify(new TicketMentioned($message)));
    }

    private function ticketUpdated(TicketUpdated $event): void
    {
        $assigneeId = $event->changes['assignee_id']['to'] ?? null;

        if (! $event->notifyAssignee || $assigneeId === null || $assigneeId === $event->actor?->id) {
            return;
        }

        User::query()->whereKey($assigneeId)->first()?->notify(new TicketAssigned($event->ticket, $event->actor));
    }
}
