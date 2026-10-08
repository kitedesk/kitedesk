<?php

namespace App\Domain\Tickets\Support;

use App\Domain\Tickets\Models\Ticket;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Hands out ticket numbers in the admin-defined format. The {seq} counter is locked while
 * it is read and advanced, so concurrent tickets never share a number.
 */
class TicketNumberGenerator
{
    public function next(?CarbonInterface $at = null): string
    {
        $at ??= now();
        $format = TicketNumberFormat::current();

        return DB::transaction(function () use ($format, $at): string {
            $scope = $format->reset->scope($at);

            for ($attempt = 0; $attempt < 50; $attempt++) {
                $sequence = $format->usesSequence() ? $this->advance($scope) : 0;
                $number = $format->render($sequence, $at);

                // Skips numbers already taken, e.g. after the counter was set back.
                if (! Ticket::query()->where('number', $number)->exists()) {
                    return $number;
                }
            }

            throw new RuntimeException('Could not find a free ticket number. Check the ticket number format.');
        });
    }

    /**
     * The value the {seq} counter will hand out next, without using it.
     */
    public function peek(?CarbonInterface $at = null): int
    {
        $scope = TicketNumberFormat::current()->reset->scope($at ?? now());

        return (int) (DB::table('ticket_number_sequences')->where('scope', $scope)->value('next') ?? 1);
    }

    /**
     * Set the value the {seq} counter hands out next in the current period.
     */
    public function setNext(int $next, ?CarbonInterface $at = null): void
    {
        $scope = TicketNumberFormat::current()->reset->scope($at ?? now());

        DB::table('ticket_number_sequences')->updateOrInsert(['scope' => $scope], ['next' => max(1, $next)]);
    }

    private function advance(string $scope): int
    {
        $row = DB::table('ticket_number_sequences')->where('scope', $scope)->lockForUpdate()->first();

        if ($row === null) {
            DB::table('ticket_number_sequences')->insertOrIgnore(['scope' => $scope, 'next' => 1]);
            $row = DB::table('ticket_number_sequences')->where('scope', $scope)->lockForUpdate()->first();
        }

        $sequence = (int) $row->next;
        DB::table('ticket_number_sequences')->where('scope', $scope)->update(['next' => $sequence + 1]);

        return $sequence;
    }
}
