<?php

namespace App\Domain\Tickets\Actions;

use App\Domain\Tickets\Events\TicketRefreshed;
use App\Domain\Tickets\Models\SatisfactionRating;
use App\Domain\Tickets\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Records the customer's answer to the satisfaction survey, from the emailed link or the
 * portal. Answering again replaces the earlier score and comment.
 */
class RateTicket
{
    public function handle(Ticket $ticket, User $customer, int $score, ?string $comment = null): SatisfactionRating
    {
        $comment = trim((string) $comment);

        return DB::transaction(function () use ($ticket, $customer, $score, $comment): SatisfactionRating {
            $rating = $ticket->satisfactionRating()->firstOrNew();
            $changed = $rating->score !== $score;

            $rating->fill([
                'user_id' => $customer->id,
                'score' => $score,
                'comment' => $comment !== '' ? $comment : null,
                'rated_at' => now(),
            ])->save();

            if ($changed) {
                activity()
                    ->performedOn($ticket)
                    ->causedBy($customer)
                    ->event('rated')
                    ->withProperties(['score' => $score])
                    ->log('rated');
            }

            $ticket->setRelation('satisfactionRating', $rating);
            TicketRefreshed::dispatch($ticket);

            return $rating;
        });
    }
}
