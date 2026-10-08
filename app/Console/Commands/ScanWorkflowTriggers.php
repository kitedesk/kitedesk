<?php

namespace App\Console\Commands;

use App\Domain\Entitlements\Enums\Feature;
use App\Domain\Entitlements\Support\PlanLimits;
use App\Domain\Tickets\Enums\TicketStatus;
use App\Domain\Tickets\Models\Ticket;
use App\Domain\Workflows\Engine\WorkflowEngine;
use App\Domain\Workflows\Enums\IdleAnchor;
use App\Domain\Workflows\Enums\WorkflowTrigger;
use App\Domain\Workflows\Models\Workflow;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Query\Builder;

#[Signature('workflows:scan')]
#[Description('Start time-based workflows for tickets that have waited long enough')]
class ScanWorkflowTriggers extends Command
{
    /**
     * Runs started per workflow and scan; the rest are picked up by the next scan.
     */
    private const int BATCH = 200;

    public function handle(WorkflowEngine $engine): int
    {
        if (! PlanLimits::allows(Feature::Workflows)) {
            return self::SUCCESS;
        }

        $started = 0;

        Workflow::query()->active()->where('trigger', WorkflowTrigger::TicketIdle)->orderBy('id')->each(function (Workflow $workflow) use ($engine, &$started): void {
            foreach ($this->dueTickets($workflow) as $ticket) {
                $engine->start($workflow, $ticket, ['event' => WorkflowTrigger::TicketIdle->value]);
                $started++;
            }
        });

        $this->components->info("Started {$started} workflow run(s).");

        return self::SUCCESS;
    }

    /**
     * Tickets idle past the threshold that haven't had a run since the anchor moment (so a
     * ticket is handled once per idle period) and are still under the run limit.
     *
     * @return iterable<Ticket>
     */
    private function dueTickets(Workflow $workflow): iterable
    {
        $settings = $workflow->triggerSettings();
        $anchor = IdleAnchor::tryFrom((string) ($settings['anchor'] ?? '')) ?? IdleAnchor::Updated;
        $column = $anchor->column();
        $amount = max(1, (int) ($settings['amount'] ?? 1));
        $threshold = ($settings['unit'] ?? 'hours') === 'days' ? now()->subDays($amount) : now()->subHours($amount);
        /** @var list<string> $statuses */
        $statuses = is_array($settings['statuses'] ?? null) && $settings['statuses'] !== []
            ? $settings['statuses']
            : array_map(fn (TicketStatus $status): string => $status->value, TicketStatus::unresolved());

        return Ticket::query()
            ->whereIn('status', $statuses)
            ->whereNull('merged_into_id')
            ->where($column, '<=', $threshold)
            ->when(! $workflow->apply_to_existing && $workflow->activated_at !== null, fn ($query) => $query->where('created_at', '>=', $workflow->activated_at))
            ->whereNotExists(fn (Builder $runs) => $runs
                ->from('workflow_runs')
                ->whereColumn('workflow_runs.ticket_id', 'tickets.id')
                ->where('workflow_runs.workflow_id', $workflow->id)
                ->whereColumn('workflow_runs.created_at', '>=', "tickets.{$column}"))
            ->whereRaw('(select count(*) from workflow_runs where workflow_runs.ticket_id = tickets.id and workflow_runs.workflow_id = ?) < ?', [$workflow->id, $workflow->runLimit()])
            ->orderBy('id')
            ->limit(self::BATCH)
            ->get();
    }
}
