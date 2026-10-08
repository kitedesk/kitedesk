<?php

use App\Domain\Sla\Models\BusinessSchedule;
use App\Domain\Sla\Models\SlaPolicy;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
});

/**
 * @return array<string, mixed>
 */
function schedulePayload(array $overrides = []): array
{
    return [
        'name' => 'Office hours',
        'timezone' => 'America/Sao_Paulo',
        'hours' => [
            '1' => [['start' => '13:00', 'end' => '18:00'], ['start' => '09:00', 'end' => '12:00']],
            '2' => [['start' => '09:00', 'end' => '24:00']],
            '6' => [],
        ],
        ...$overrides,
    ];
}

function makeSchedule(): BusinessSchedule
{
    return BusinessSchedule::query()->create([
        'name' => 'Office',
        'timezone' => 'UTC',
        'hours' => [1 => [['start' => '09:00', 'end' => '17:00']]],
    ]);
}

test('non administrators cannot manage business hours', function () {
    $this->actingAs(User::factory()->agent()->create())
        ->get(route('admin.business-schedules.index'))
        ->assertForbidden();
});

test('admins can create a schedule with sorted intervals per weekday', function () {
    $response = $this->actingAs($this->admin)->post(route('admin.business-schedules.store'), schedulePayload());

    $schedule = BusinessSchedule::query()->sole();
    $response->assertRedirect(route('admin.business-schedules.edit', $schedule));

    expect($schedule->timezone)->toBe('America/Sao_Paulo')
        ->and($schedule->hours)->toBe([
            '1' => [['start' => '09:00', 'end' => '12:00'], ['start' => '13:00', 'end' => '18:00']],
            '2' => [['start' => '09:00', 'end' => '24:00']],
        ]);
});

test('the edit page lists every weekday and the holidays', function () {
    $schedule = makeSchedule();
    $schedule->holidays()->create(['name' => 'Christmas', 'date' => '2026-12-25']);

    $this->actingAs($this->admin)
        ->get(route('admin.business-schedules.edit', $schedule))
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/sla/schedules/form')
            ->has('schedule.hours', 7)
            ->where('schedule.hours.1.0.start', '09:00')
            ->where('schedule.holidays.0.date', '2026-12-25'));
});

test('invalid intervals are rejected', function (array $hours, string $error) {
    $this->actingAs($this->admin)
        ->post(route('admin.business-schedules.store'), schedulePayload(['hours' => $hours]))
        ->assertSessionHasErrors($error);

    expect(BusinessSchedule::query()->count())->toBe(0);
})->with([
    'end before start' => [['1' => [['start' => '17:00', 'end' => '09:00']]], 'hours.1.0.end'],
    'equal start and end' => [['1' => [['start' => '09:00', 'end' => '09:00']]], 'hours.1.0.end'],
    'bad time format' => [['1' => [['start' => '9am', 'end' => '17:00']]], 'hours.1.0.start'],
    'start at 24:00' => [['1' => [['start' => '24:00', 'end' => '24:00']]], 'hours.1.0.start'],
    'invalid minutes' => [['1' => [['start' => '09:00', 'end' => '17:75']]], 'hours.1.0.end'],
    'overlapping intervals' => [['1' => [['start' => '09:00', 'end' => '13:00'], ['start' => '12:00', 'end' => '17:00']]], 'hours.1.1.start'],
    'unknown weekday' => [['8' => [['start' => '09:00', 'end' => '17:00']]], 'hours.8'],
]);

test('an unknown timezone is rejected', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.business-schedules.store'), schedulePayload(['timezone' => 'Mars/Olympus']))
        ->assertSessionHasErrors('timezone');
});

test('admins can update a schedule', function () {
    $schedule = makeSchedule();

    $this->actingAs($this->admin)
        ->put(route('admin.business-schedules.update', $schedule), schedulePayload(['name' => 'Updated', 'hours' => ['5' => [['start' => '10:00', 'end' => '14:00']]]]))
        ->assertRedirect(route('admin.business-schedules.edit', $schedule));

    $schedule->refresh();
    expect($schedule->name)->toBe('Updated')
        ->and($schedule->hours)->toBe(['5' => [['start' => '10:00', 'end' => '14:00']]]);
});

test('deleting a schedule makes its policies fall back to calendar time', function () {
    $schedule = makeSchedule();
    $policy = SlaPolicy::factory()->create(['business_schedule_id' => $schedule->id]);

    $this->actingAs($this->admin)
        ->delete(route('admin.business-schedules.destroy', $schedule))
        ->assertRedirect(route('admin.business-schedules.index'));

    expect(BusinessSchedule::query()->count())->toBe(0)
        ->and($policy->refresh()->business_schedule_id)->toBeNull();
});

test('admins can add and remove holidays', function () {
    $schedule = makeSchedule();

    $this->actingAs($this->admin)
        ->post(route('admin.business-schedules.holidays.store', $schedule), ['name' => 'New Year', 'date' => '2027-01-01'])
        ->assertSessionHasNoErrors();

    $holiday = $schedule->holidays()->sole();
    expect($holiday->date->toDateString())->toBe('2027-01-01');

    $this->actingAs($this->admin)
        ->post(route('admin.business-schedules.holidays.store', $schedule), ['name' => 'Duplicate', 'date' => '2027-01-01'])
        ->assertSessionHasErrors('date');

    $this->actingAs($this->admin)
        ->post(route('admin.business-schedules.holidays.store', $schedule), ['name' => 'Bad', 'date' => '01/02/2027'])
        ->assertSessionHasErrors('date');

    $this->actingAs($this->admin)
        ->delete(route('admin.business-schedules.holidays.destroy', [$schedule, $holiday]))
        ->assertRedirect();

    expect($schedule->holidays()->count())->toBe(0);
});

test('holidays cannot be removed through another schedule', function () {
    $schedule = makeSchedule();
    $other = makeSchedule();
    $holiday = $schedule->holidays()->create(['name' => 'Christmas', 'date' => '2026-12-25']);

    $this->actingAs($this->admin)
        ->delete(route('admin.business-schedules.holidays.destroy', [$other, $holiday]))
        ->assertNotFound();

    expect($holiday->fresh())->not->toBeNull();
});
