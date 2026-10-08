<?php

namespace App\Domain\Tickets\Events;

use App\Domain\Support\Broadcasting\Channels;
use App\Domain\Tickets\Models\TicketMessage;
use App\Domain\Workflows\Support\CarriesWorkflowOrigin;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageCreated implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use CarriesWorkflowOrigin, Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public TicketMessage $message)
    {
        // The tab that made the change already has fresh data from its own response.
        $this->dontBroadcastToCurrentUser();
        $this->captureWorkflowOrigin();
    }

    /**
     * Internal notes are only announced on the staff channel.
     *
     * @return list<PrivateChannel>
     */
    public function broadcastOn(): array
    {
        $channels = [
            new PrivateChannel(Channels::name('staff.tickets')),
            new PrivateChannel(Channels::name('staff.tickets.'.$this->message->ticket_id)),
        ];

        if (! $this->message->is_internal) {
            $channels = [...$channels, ...$this->message->ticket->customerChannels()];
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'message.created';
    }

    /**
     * @return array{ticket_id: int, message_id: int}
     */
    public function broadcastWith(): array
    {
        return ['ticket_id' => $this->message->ticket_id, 'message_id' => $this->message->id];
    }
}
