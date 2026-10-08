<?php

namespace App\Domain\Tickets\Events;

use App\Domain\Support\Broadcasting\Channels;
use App\Domain\Tickets\Models\Ticket;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Tells open pages to reload a ticket after a change that bypasses UpdateTicket (a rating,
 * an SLA breach, messages moved by a merge). Broadcast only: nothing listens to it, so it
 * never fires webhooks, workflows or notifications.
 */
class TicketRefreshed implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Ticket $ticket, public bool $customerVisible = true)
    {
        // The tab that made the change already has fresh data from its own response.
        $this->dontBroadcastToCurrentUser();
    }

    /**
     * @return list<PrivateChannel>
     */
    public function broadcastOn(): array
    {
        $channels = [
            new PrivateChannel(Channels::name('staff.tickets')),
            new PrivateChannel(Channels::name('staff.tickets.'.$this->ticket->id)),
        ];

        return $this->customerVisible ? [...$channels, ...$this->ticket->customerChannels()] : $channels;
    }

    /**
     * Same name as TicketUpdated, so pages already listening reload without changes.
     */
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
