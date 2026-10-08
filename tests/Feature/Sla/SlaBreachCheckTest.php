<?php

use App\Domain\Sla\Models\BusinessSchedule;
use App\Domain\Sla\Models\SlaPolicy;
use App\Domain\Sla\Notifications\SlaBreached;
use App\Domain\Tickets\Enums\TicketStatus;
use App\Domain\Tickets\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;

test('only new SLA breaches are flagged and notified', function () {
    Notification::fake();
    $this->freezeSecond();
    $agent = User::factory()->agent()->create();

    $overdue = Ticket::factory()->assignedTo($agent)->create(['first_response_due_at' => now()->subHour()]);
    $alreadyFlagged = Ticket::factory()->assignedTo($agent)->create(['first_response_due_at' => now()->subHours(2), 'sla_breached_at' => now()->subHour()]);
    $missedAgain = Ticket::factory()->assignedTo($agent)->create(['next_reply_due_at' => now()->subMinutes(5), 'sla_breached_at' => now()->subDay()]);
    $notDue = Ticket::factory()->assignedTo($agent)->create(['resolution_due_at' => now()->addHour()]);
    $solved = Ticket::factory()->assignedTo($agent)->status(TicketStatus::Solved)->create(['first_response_due_at' => now()->subHour()]);

    $this->artisan('sla:check-breaches')->assertSuccessful();

    Notification::assertSentToTimes($agent, SlaBreached::class, 2);
    expect($overdue->refresh()->sla_breached_at?->equalTo(now()))->toBeTrue()
        ->and($missedAgain->refresh()->sla_breached_at?->equalTo(now()))->toBeTrue()
        ->and($alreadyFlagged->refresh()->sla_breached_at?->equalTo(now()->subHour()))->toBeTrue()
        ->and($notDue->refresh()->sla_breached_at)->toBeNull()
        ->and($solved->refresh()->sla_breached_at)->toBeNull();
});

test('cached SLA policies come back as models with their schedule and holidays', function () {
    config(['cache.stores.array.serialize' => true]);
    Cache::forgetDriver('array');

    $schedule = BusinessSchedule::query()->create(['name' => 'Office', 'timezone' => 'UTC', 'hours' => [1 => [['start' => '09:00', 'end' => '17:00']]]]);
    $schedule->holidays()->create(['name' => 'New year', 'date' => '2027-01-01']);
    SlaPolicy::factory()->create(['business_schedule_id' => $schedule->id]);

    SlaPolicy::forEvaluation();
    $policy = SlaPolicy::forEvaluation()->sole();

    expect($policy)->toBeInstanceOf(SlaPolicy::class)
        ->and($policy->businessSchedule?->name)->toBe('Office')
        ->and($policy->businessSchedule?->holidays->sole()->date->toDateString())->toBe('2027-01-01');
});
