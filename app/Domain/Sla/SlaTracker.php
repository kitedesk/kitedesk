<?php

namespace App\Domain\Sla;

use App\Domain\Entitlements\Enums\Feature;
use App\Domain\Entitlements\Support\PlanLimits;
use App\Domain\Sla\Models\SlaPolicy;
use App\Domain\Tickets\Enums\TicketStatus;
use App\Domain\Tickets\Models\Ticket;
use Carbon\CarbonImmutable;

/**
 * Keeps a ticket's SLA due dates in sync with what happens to it.
 *
 * Metrics:
 *  - first_response: from creation until the first public staff reply.
 *  - next_reply: from each customer reply (after the first response) until the next public staff reply.
 *  - resolution: from creation until solved; paused while the ticket is pending or on-hold.
 *
 * Methods only mutate attributes; callers are responsible for saving the ticket.
 */
class SlaTracker
{
    public function __construct(private SlaCalculator $calculator) {}

    /**
     * Pick the first matching policy and compute due dates from the ticket's creation time.
     */
    public function apply(Ticket $ticket): void
    {
        $policy = $this->policyFor($ticket);
        $ticket->sla_policy_id = $policy?->id;
        $ticket->setRelation('slaPolicy', $policy);

        if ($policy === null || ! $ticket->status->isUnresolved()) {
            $this->clear($ticket);

            return;
        }

        $createdAt = CarbonImmutable::instance($ticket->created_at ?? now());

        $ticket->first_response_due_at = $ticket->first_responded_at === null
            ? $this->due($policy, 'first_response', $ticket, $createdAt)
            : null;

        if ($ticket->status->pausesSla()) {
            $ticket->resolution_due_at = null;
            $ticket->resolution_remaining_minutes = $this->target($policy, 'resolution', $ticket);
        } else {
            $ticket->resolution_due_at = $this->due($policy, 'resolution', $ticket, $createdAt);
            $ticket->resolution_remaining_minutes = null;
        }
    }

    /**
     * A public staff reply satisfies the first response and next reply targets.
     */
    public function recordStaffReply(Ticket $ticket): void
    {
        $ticket->first_response_due_at = null;
        $ticket->next_reply_due_at = null;
    }

    /**
     * A customer reply after the first response starts the next reply clock.
     */
    public function recordCustomerReply(Ticket $ticket): void
    {
        $policy = $ticket->slaPolicy;

        if ($policy === null || $ticket->first_responded_at === null || ! $ticket->status->isUnresolved()) {
            return;
        }

        $ticket->next_reply_due_at ??= $this->due($policy, 'next_reply', $ticket, CarbonImmutable::now());
    }

    /**
     * Pause, resume, stop or restart clocks when the status changes.
     */
    public function recordStatusChange(Ticket $ticket, TicketStatus $from, TicketStatus $to): void
    {
        $policy = $ticket->slaPolicy;

        if ($policy === null) {
            return;
        }

        if (! $to->isUnresolved()) {
            $this->clear($ticket);

            return;
        }

        $now = CarbonImmutable::now();
        $schedule = $policy->businessSchedule;

        if (! $from->isUnresolved()) {
            $ticket->resolution_remaining_minutes = null;
            $ticket->resolution_due_at = $to->pausesSla() ? null : $this->due($policy, 'resolution', $ticket, $now);

            if ($to->pausesSla()) {
                $ticket->resolution_remaining_minutes = $this->target($policy, 'resolution', $ticket);
            }

            return;
        }

        if ($to->pausesSla() && ! $from->pausesSla() && $ticket->resolution_due_at !== null) {
            $ticket->resolution_remaining_minutes = $this->calculator->minutesBetween($now, $ticket->resolution_due_at, $schedule);
            $ticket->resolution_due_at = null;
            $ticket->next_reply_due_at = null;
        }

        if (! $to->pausesSla() && $from->pausesSla() && $ticket->resolution_remaining_minutes !== null) {
            $ticket->resolution_due_at = $this->calculator->addMinutes($now, $ticket->resolution_remaining_minutes, $schedule);
            $ticket->resolution_remaining_minutes = null;
        }
    }

    /**
     * The first policy that matches, or none when the plan leaves SLAs out.
     */
    public function policyFor(Ticket $ticket): ?SlaPolicy
    {
        if (! PlanLimits::allows(Feature::Sla)) {
            return null;
        }

        return SlaPolicy::forEvaluation()->first(fn (SlaPolicy $policy): bool => $policy->matches($ticket));
    }

    private function clear(Ticket $ticket): void
    {
        $ticket->first_response_due_at = null;
        $ticket->next_reply_due_at = null;
        $ticket->resolution_due_at = null;
        $ticket->resolution_remaining_minutes = null;
    }

    /**
     * @param  'first_response'|'next_reply'|'resolution'  $metric
     */
    private function target(SlaPolicy $policy, string $metric, Ticket $ticket): ?int
    {
        return $policy->targetMinutes($metric, $ticket->priority);
    }

    /**
     * @param  'first_response'|'next_reply'|'resolution'  $metric
     */
    private function due(SlaPolicy $policy, string $metric, Ticket $ticket, CarbonImmutable $from): ?CarbonImmutable
    {
        $minutes = $this->target($policy, $metric, $ticket);

        return $minutes === null ? null : $this->calculator->addMinutes($from, $minutes, $policy->businessSchedule);
    }
}
