<?php

namespace App\Domain\Workflows\Nodes\Concerns;

use App\Domain\Sla\SlaCalculator;
use App\Domain\Tickets\Models\Ticket;
use Carbon\CarbonImmutable;
use Illuminate\Validation\Rule;

/**
 * Shared "wait N minutes/hours/days" settings, optionally counted in the ticket's business hours.
 */
trait WaitsForDuration
{
    /**
     * @return array<string, mixed>
     */
    protected function durationRules(): array
    {
        return [
            'amount' => ['required', 'integer', 'min:1', 'max:1000'],
            'unit' => ['required', Rule::in(['minutes', 'hours', 'days'])],
            'business_hours' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function until(array $data, Ticket $ticket): CarbonImmutable
    {
        $minutes = (int) $data['amount'] * match ($data['unit'] ?? 'minutes') {
            'days' => 1440,
            'hours' => 60,
            default => 1,
        };

        $schedule = ($data['business_hours'] ?? false) ? $ticket->slaPolicy?->businessSchedule : null;

        return app(SlaCalculator::class)->addMinutes(CarbonImmutable::now(), $minutes, $schedule);
    }
}
