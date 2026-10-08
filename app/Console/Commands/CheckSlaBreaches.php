<?php

namespace App\Console\Commands;

use App\Domain\Accounts\Enums\Permission;
use App\Domain\Entitlements\Enums\Feature;
use App\Domain\Entitlements\Support\PlanLimits;
use App\Domain\Sla\Events\SlaTargetMissed;
use App\Domain\Sla\Notifications\SlaBreached;
use App\Domain\Tickets\Events\TicketRefreshed;
use App\Domain\Tickets\Models\Ticket;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Notification;

#[Signature('sla:check-breaches')]
#[Description('Flag tickets that missed an SLA target and notify the responsible agents')]
class CheckSlaBreaches extends Command
{
    private const array DUE_COLUMNS = ['first_response_due_at', 'next_reply_due_at', 'resolution_due_at'];

    /**
     * @var Collection<int, User>|null
     */
    private ?Collection $admins = null;

    public function handle(): int
    {
        if (! PlanLimits::allows(Feature::Sla)) {
            return self::SUCCESS;
        }

        // The command object can be reused (e.g. once per workspace), so start fresh each run.
        $this->admins = null;
        $now = now();
        $flagged = 0;

        // Only targets missed since the last recorded breach, so tickets already flagged aren't rescanned every run.
        Ticket::query()
            ->unresolved()
            ->where(function (Builder $query) use ($now): void {
                foreach (self::DUE_COLUMNS as $column) {
                    $query->orWhere(fn (Builder $missed) => $missed
                        ->where($column, '<=', $now)
                        ->where(fn (Builder $unflagged) => $unflagged->whereNull('sla_breached_at')->orWhereColumn('sla_breached_at', '<', $column)));
                }
            })
            ->with(['assignee', 'group.agents'])
            ->chunkById(200, function ($tickets) use ($now, &$flagged): void {
                foreach ($tickets as $ticket) {
                    if (! $this->isNewBreach($ticket, $now)) {
                        continue;
                    }

                    $ticket->forceFill(['sla_breached_at' => $now])->saveQuietly();
                    Notification::send($this->recipientsFor($ticket), new SlaBreached($ticket));
                    SlaTargetMissed::dispatch($ticket);
                    TicketRefreshed::dispatch($ticket, customerVisible: false);
                    $flagged++;
                }
            });

        $this->components->info("Flagged {$flagged} ticket(s) breaching their SLA.");

        return self::SUCCESS;
    }

    /**
     * A breach is new when no breach was recorded since the earliest missed target.
     */
    private function isNewBreach(Ticket $ticket, CarbonImmutable $now): bool
    {
        $earliestMissed = collect([$ticket->first_response_due_at, $ticket->next_reply_due_at, $ticket->resolution_due_at])
            ->filter(fn (?CarbonImmutable $due): bool => $due !== null && $due->lessThanOrEqualTo($now))
            ->min();

        return $earliestMissed !== null
            && ($ticket->sla_breached_at === null || $ticket->sla_breached_at->lessThan($earliestMissed));
    }

    /**
     * The assignee, otherwise the ticket's group, otherwise the administrators.
     *
     * @return iterable<User>
     */
    private function recipientsFor(Ticket $ticket): iterable
    {
        if ($ticket->assignee !== null) {
            return [$ticket->assignee];
        }

        if ($ticket->group !== null && $ticket->group->agents->isNotEmpty()) {
            return $ticket->group->agents;
        }

        return $this->admins ??= User::query()->staff()->permission(Permission::ManageSla->value)->get();
    }
}
