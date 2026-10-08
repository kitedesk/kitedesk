<?php

namespace App\Domain\Tickets\Events;

use App\Domain\Support\Broadcasting\Channels;
use App\Domain\Tickets\Models\Ticket;
use App\Domain\Workflows\Support\CarriesWorkflowOrigin;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcasts only identifiers: clients reload data through authorized requests,
 * so nothing confidential (e.g. internal notes) ever travels over the socket.
 */
class TicketCreated implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use CarriesWorkflowOrigin, Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Ticket $ticket)
    {
        // The tab that made the change already has fresh data from its own response.
        $this->dontBroadcastToCurrentUser();
        $this->captureWorkflowOrigin();
    }

    /**
     * @return list<PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel(Channels::name('staff.tickets')), ...$this->ticket->customerChannels()];
    }

    public function broadcastAs(): string
    {
        return 'ticket.created';
    }

    /**
     * @return array{ticket_id: int}
     */
    public function broadcastWith(): array
    {
        return ['ticket_id' => $this->ticket->id];
    }
}
