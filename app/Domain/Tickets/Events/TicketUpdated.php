<?php

namespace App\Domain\Tickets\Events;

use App\Domain\Support\Broadcasting\Channels;
use App\Domain\Tickets\Models\Ticket;
use App\Domain\Workflows\Support\CarriesWorkflowOrigin;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TicketUpdated implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use CarriesWorkflowOrigin, Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Changes that show on the customer's pages. The custom status is left out on purpose:
     * customers only see its category, which comes through as `status`.
     */
    public const CUSTOMER_VISIBLE = ['subject', 'status', 'assignee_id', 'collaborators'];

    /**
     * @param  array<string, array{from: mixed, to: mixed}>  $changes
     * @param  bool  $notifyAssignee  False when the caller sends its own notice (a forwarded ticket).
     */
    public function __construct(public Ticket $ticket, public array $changes, public ?User $actor = null, public bool $notifyAssignee = true)
    {
        // The tab that made the change already has fresh data from its own response.
        $this->dontBroadcastToCurrentUser();
        $this->captureWorkflowOrigin();
    }

    /**
     * Customers only hear about changes they can see, including people just removed from
     * the CC list (so the ticket leaves their list).
     *
     * @return list<PrivateChannel>
     */
    public function broadcastOn(): array
    {
        $channels = [
            new PrivateChannel(Channels::name('staff.tickets')),
            new PrivateChannel(Channels::name('staff.tickets.'.$this->ticket->id)),
        ];

        if (array_intersect(array_keys($this->changes), self::CUSTOMER_VISIBLE) === []) {
            return $channels;
        }

        /** @var list<string> $removedEmails */
        $removedEmails = $this->changes['collaborators']['from'] ?? [];
        $removed = $removedEmails === []
            ? []
            : array_values(array_map(intval(...), User::query()->whereIn('email', $removedEmails)->pluck('id')->all()));

        return [...$channels, ...$this->ticket->customerChannels($removed)];
    }

    public function broadcastAs(): string
    {
        return 'ticket.updated';
    }

    /**
     * @return array{ticket_id: int}
     */
    public function broadcastWith(): array
    {
        return ['ticket_id' => $this->ticket->id];
    }
}
