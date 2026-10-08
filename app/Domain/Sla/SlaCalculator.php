<?php

namespace App\Domain\Sla;

use App\Domain\Sla\Models\BusinessSchedule;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Measures SLA time in business minutes.
 *
 * A null schedule means the clock runs 24/7 (calendar minutes).
 */
class SlaCalculator
{
    /**
     * Maximum number of days scanned before giving up (protects against empty schedules).
     */
    private const MAX_DAYS = 3 * 366;

    /**
     * Add business minutes to a starting moment and return the resulting moment.
     */
    public function addMinutes(CarbonInterface $start, int $minutes, ?BusinessSchedule $schedule): CarbonImmutable
    {
        $start = CarbonImmutable::instance($start);

        if ($schedule === null || ! $this->hasWorkingHours($schedule)) {
            return $start->addMinutes($minutes);
        }

        $timezone = $schedule->timezone;
        $cursor = $start->setTimezone($timezone);
        $remaining = $minutes;
        $holidays = $this->holidayDates($schedule);

        for ($day = 0; $day < self::MAX_DAYS; $day++) {
            $startOfDay = $cursor->startOfDay();

            foreach ($this->intervalsFor($cursor, $schedule, $holidays) as [$opensAt, $closesAt]) {
                if ($cursor->greaterThanOrEqualTo($closesAt)) {
                    continue;
                }

                $effectiveStart = $cursor->max($opensAt);
                $available = (int) $effectiveStart->diffInMinutes($closesAt);

                if ($remaining <= $available) {
                    return $effectiveStart->addMinutes($remaining)->setTimezone($start->getTimezone());
                }

                $remaining -= $available;
                $cursor = $closesAt;
            }

            $cursor = $startOfDay->addDay();
        }

        return $start->addMinutes($minutes);
    }

    /**
     * Count the business minutes elapsed between two moments (0 if end is before start).
     */
    public function minutesBetween(CarbonInterface $start, CarbonInterface $end, ?BusinessSchedule $schedule): int
    {
        $start = CarbonImmutable::instance($start);
        $end = CarbonImmutable::instance($end);

        if ($end->lessThanOrEqualTo($start)) {
            return 0;
        }

        if ($schedule === null || ! $this->hasWorkingHours($schedule)) {
            return (int) $start->diffInMinutes($end);
        }

        $timezone = $schedule->timezone;
        $cursor = $start->setTimezone($timezone);
        $end = $end->setTimezone($timezone);
        $holidays = $this->holidayDates($schedule);
        $total = 0;

        while ($cursor->lessThan($end)) {
            foreach ($this->intervalsFor($cursor, $schedule, $holidays) as [$opensAt, $closesAt]) {
                $from = $cursor->max($opensAt);
                $to = $end->min($closesAt);

                if ($to->greaterThan($from)) {
                    $total += (int) $from->diffInMinutes($to);
                }
            }

            $cursor = $cursor->addDay()->startOfDay();
        }

        return $total;
    }

    /**
     * Working intervals for the day containing the given moment, in the schedule's timezone.
     *
     * @param  array<string, true>  $holidays
     * @return list<array{0: CarbonImmutable, 1: CarbonImmutable}>
     */
    private function intervalsFor(CarbonImmutable $moment, BusinessSchedule $schedule, array $holidays): array
    {
        if (isset($holidays[$moment->toDateString()])) {
            return [];
        }

        $intervals = [];

        foreach ($schedule->hours[$moment->isoWeekday()] ?? [] as $interval) {
            $opensAt = $moment->setTimeFromTimeString($interval['start']);
            $closesAt = $interval['end'] === '24:00'
                ? $moment->addDay()->startOfDay()
                : $moment->setTimeFromTimeString($interval['end']);

            if ($closesAt->greaterThan($opensAt)) {
                $intervals[] = [$opensAt, $closesAt];
            }
        }

        usort($intervals, fn (array $a, array $b): int => $a[0] <=> $b[0]);

        return $intervals;
    }

    private function hasWorkingHours(BusinessSchedule $schedule): bool
    {
        return collect($schedule->hours)->flatten(1)->isNotEmpty();
    }

    /**
     * @return array<string, true>
     */
    private function holidayDates(BusinessSchedule $schedule): array
    {
        return $schedule->holidays
            ->mapWithKeys(fn ($holiday): array => [$holiday->date->toDateString() => true])
            ->all();
    }
}
