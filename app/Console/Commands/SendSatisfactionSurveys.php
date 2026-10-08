<?php

namespace App\Console\Commands;

use App\Domain\Tickets\Enums\TicketStatus;
use App\Domain\Tickets\Models\SatisfactionRating;
use App\Domain\Tickets\Models\Ticket;
use App\Domain\Tickets\Support\SatisfactionSurvey;
use App\Mail\SatisfactionSurveyEmail;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Mail;

#[Signature('tickets:send-satisfaction-surveys')]
#[Description('Email the satisfaction survey for tickets solved long enough ago')]
class SendSatisfactionSurveys extends Command
{
    public function handle(): int
    {
        $survey = SatisfactionSurvey::current();

        if (! $survey->enabled || $survey->enabledSince === null) {
            return self::SUCCESS;
        }

        $sent = 0;

        // A ticket reopened while waiting is no longer solved; when it is solved again the
        // delay starts over from the new solved_at. Each ticket is surveyed at most once.
        Ticket::query()
            ->whereIn('status', [TicketStatus::Solved, TicketStatus::Closed])
            ->whereNull('merged_into_id')
            ->where('solved_at', '>=', $survey->enabledSince)
            ->where('solved_at', '<=', now()->subHours($survey->delayHours))
            ->whereDoesntHave('satisfactionRating')
            ->whereHas('requester', fn (Builder $requester) => $requester->customers())
            ->with('requester')
            ->chunkById(200, function (Collection $tickets) use (&$sent): void {
                foreach ($tickets as $ticket) {
                    $rating = SatisfactionRating::query()->create([
                        'ticket_id' => $ticket->id,
                        'user_id' => $ticket->requester_id,
                        'sent_at' => now(),
                    ]);

                    Mail::to($ticket->requester->email, $ticket->requester->name)
                        ->queue(new SatisfactionSurveyEmail($rating));
                    $sent++;
                }
            });

        $this->components->info("Sent {$sent} satisfaction survey(s).");

        return self::SUCCESS;
    }
}
