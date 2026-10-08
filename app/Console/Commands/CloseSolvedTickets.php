<?php

namespace App\Console\Commands;

use App\Domain\Tickets\Actions\UpdateTicket;
use App\Domain\Tickets\Enums\TicketStatus;
use App\Domain\Tickets\Models\Ticket;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('tickets:close-solved {--days=4 : Days a ticket stays solved before it is closed}')]
#[Description('Close tickets that have been solved for a number of days')]
class CloseSolvedTickets extends Command
{
    public function handle(UpdateTicket $updateTicket): int
    {
        $closed = 0;

        Ticket::query()
            ->where('status', TicketStatus::Solved)
            ->where('solved_at', '<=', now()->subDays((int) $this->option('days')))
            ->chunkById(200, function ($tickets) use ($updateTicket, &$closed): void {
                foreach ($tickets as $ticket) {
                    $updateTicket->handle($ticket, ['status' => TicketStatus::Closed]);
                    $closed++;
                }
            });

        $this->components->info("Closed {$closed} ticket(s).");

        return self::SUCCESS;
    }
}
