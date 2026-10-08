<?php

namespace App\Domain\Tickets\Support;

use App\Domain\Tickets\Models\Ticket;
use App\Models\User;

/**
 * A request sent without signing in. `isNewPerson` is false when the email already belonged
 * to someone: the sender may not own it, so they must not get access to the ticket.
 */
final readonly class GuestSubmission
{
    public function __construct(
        public Ticket $ticket,
        public User $requester,
        public bool $isNewPerson,
    ) {}
}
