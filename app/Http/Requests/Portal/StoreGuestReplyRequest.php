<?php

namespace App\Http\Requests\Portal;

use App\Domain\Tickets\Enums\TicketStatus;
use App\Domain\Tickets\Models\Ticket;
use App\Domain\Tickets\Support\GuestAccess;

/**
 * A reply from someone following a request through a magic link.
 */
class StoreGuestReplyRequest extends StoreReplyRequest
{
    public function authorize(): bool
    {
        $ticket = $this->route('ticket');

        return $ticket instanceof Ticket
            && $ticket->status !== TicketStatus::Closed
            && GuestAccess::userFor($this, $ticket) !== null;
    }
}
