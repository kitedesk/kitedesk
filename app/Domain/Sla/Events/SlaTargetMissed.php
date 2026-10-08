<?php

namespace App\Domain\Sla\Events;

use App\Domain\Tickets\Models\Ticket;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A ticket missed one of its SLA targets (dispatched by `sla:check-breaches`).
 */
class SlaTargetMissed
{
    use Dispatchable, SerializesModels;

    public function __construct(public Ticket $ticket) {}
}
