<?php

use App\Domain\Sla\Models\BusinessSchedule;
use App\Domain\Sla\Models\Holiday;
use App\Domain\Sla\SlaCalculator;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;

/**
 * @param  array<int, list<array{start: string, end: string}>>  $hours
 * @param  list<string>  $holidays
 */
function schedule(array $hours, string $timezone = 'America/Sao_Paulo', array $holidays = []): BusinessSchedule
{
    $schedule = new BusinessSchedule(['name' => 'Test', 'timezone' => $timezone, 'hours' => $hours]);

    return $schedule->setRelation('holidays', new Collection(array_map(
        fn (string $date): Holiday => new Holiday(['name' => 'Holiday', 'date' => $date]),
        $holidays,
    )));
}

function weekdays(string $start = '09:00', string $end = '17:00'): array
{
    return array_fill_keys([1, 2, 3, 4, 5], [['start' => $start, 'end' => $end]]);
}

function at(string $moment, string $timezone = 'America/Sao_Paulo'): CarbonImmutable
{
    return CarbonImmutable::parse($moment, $timezone);
}

test('without a schedule the clock runs on calendar time', function () {
    $due = (new SlaCalculator)->addMinutes(at('2026-10-09 16:00'), 120, null);

    expect($due->equalTo(at('2026-10-09 18:00')))->toBeTrue();
});

test('business minutes roll over the weekend', function () {
    // Friday 16:00 + 2h of business time = Monday 10:00.
    $due = (new SlaCalculator)->addMinutes(at('2026-10-09 16:00'), 120, schedule(weekdays()));

    expect($due->equalTo(at('2026-10-12 10:00')))->toBeTrue();
});

test('the clock starts at opening time when the ticket arrives early', function () {
    $due = (new SlaCalculator)->addMinutes(at('2026-10-12 07:00'), 30, schedule(weekdays()));

    expect($due->equalTo(at('2026-10-12 09:30')))->toBeTrue();
});

test('holidays are skipped', function () {
    $due = (new SlaCalculator)->addMinutes(at('2026-10-09 16:00'), 120, schedule(weekdays(), holidays: ['2026-10-12']));

    expect($due->equalTo(at('2026-10-13 10:00')))->toBeTrue();
});

test('lunch breaks between intervals are skipped', function () {
    $hours = array_fill_keys([1, 2, 3, 4, 5], [['start' => '09:00', 'end' => '12:00'], ['start' => '13:00', 'end' => '17:00']]);

    $due = (new SlaCalculator)->addMinutes(at('2026-10-12 11:30'), 60, schedule($hours));

    expect($due->equalTo(at('2026-10-12 13:30')))->toBeTrue();
});

test('the result keeps the timezone of the starting moment', function () {
    $start = at('2026-10-12 12:00', 'UTC'); // 09:00 in São Paulo

    $due = (new SlaCalculator)->addMinutes($start, 60, schedule(weekdays()));

    expect($due->getTimezone()->getName())->toBe('UTC')
        ->and($due->equalTo(at('2026-10-12 13:00', 'UTC')))->toBeTrue();
});

test('intervals that end at midnight continue into the next day', function () {
    $hours = [1 => [['start' => '00:00', 'end' => '24:00']], 2 => [['start' => '00:00', 'end' => '24:00']]];

    $due = (new SlaCalculator)->addMinutes(at('2026-10-12 23:00'), 120, schedule($hours));

    expect($due->equalTo(at('2026-10-13 01:00')))->toBeTrue();
});

test('a schedule without working hours falls back to calendar time', function () {
    $due = (new SlaCalculator)->addMinutes(at('2026-10-12 10:00'), 30, schedule([]));

    expect($due->equalTo(at('2026-10-12 10:30')))->toBeTrue();
});

test('elapsed business minutes exclude nights and weekends', function () {
    $minutes = (new SlaCalculator)->minutesBetween(at('2026-10-09 16:00'), at('2026-10-12 10:00'), schedule(weekdays()));

    expect($minutes)->toBe(120);
});

test('elapsed business minutes are zero when the end precedes the start', function () {
    $minutes = (new SlaCalculator)->minutesBetween(at('2026-10-12 10:00'), at('2026-10-12 09:00'), schedule(weekdays()));

    expect($minutes)->toBe(0);
});
