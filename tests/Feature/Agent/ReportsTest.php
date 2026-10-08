<?php

use App\Domain\Accounts\Models\Group;
use App\Domain\Reports\TicketReport;
use App\Domain\Sla\Models\SlaPolicy;
use App\Domain\Tickets\Enums\TicketStatus;
use App\Domain\Tickets\Models\Ticket;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->travelTo('2026-10-12 12:00:00');
    $this->agent = User::factory()->agent()->create(['name' => 'Ana']);
    $this->group = Group::factory()->create(['name' => 'Billing']);
    $policy = SlaPolicy::factory()->create();

    // Created 2 days ago, first reply after 30 minutes, solved after 2 hours, SLA kept.
    Ticket::factory()->assignedTo($this->agent)->status(TicketStatus::Solved)->create([
        'group_id' => $this->group->id,
        'sla_policy_id' => $policy->id,
        'created_at' => '2026-10-10 09:00:00',
        'first_responded_at' => '2026-10-10 09:30:00',
        'solved_at' => '2026-10-10 11:00:00',
    ]);
    // Created yesterday, first reply after 90 minutes, solved today after a day, SLA breached.
    Ticket::factory()->assignedTo($this->agent)->status(TicketStatus::Solved)->create([
        'sla_policy_id' => $policy->id,
        'created_at' => '2026-10-11 10:00:00',
        'first_responded_at' => '2026-10-11 11:30:00',
        'solved_at' => '2026-10-12 10:00:00',
        'sla_breached_at' => '2026-10-12 09:00:00',
    ]);
    // Still open, created today, no reply yet.
    Ticket::factory()->create(['created_at' => '2026-10-12 08:00:00']);
    // Created long before the range: only counts in the backlog.
    Ticket::factory()->status(TicketStatus::Open)->create(['created_at' => '2026-08-01 08:00:00']);
});

test('the report summarizes volume, speed and SLA for the period', function () {
    $report = (new TicketReport(now()->subDays(6)->toImmutable(), now()->toImmutable()))->build();

    expect($report['totals'])->toBe([
        'created' => 3,
        'solved' => 2,
        'backlog' => 2,
        'median_first_response_minutes' => 60,
        'median_resolution_minutes' => 780,
        'sla_compliance' => 50.0,
        'sla_measured' => 2,
        'satisfaction' => null,
        'satisfaction_responses' => 0,
    ])
        ->and($report['daily'])->toHaveCount(7)
        ->and(collect($report['daily'])->firstWhere('date', '2026-10-12'))->toBe(['date' => '2026-10-12', 'created' => 1, 'solved' => 1])
        ->and($report['breakdowns']['agent'][0])->toMatchArray(['label' => 'Ana', 'created' => 2, 'solved' => 2])
        ->and(collect($report['breakdowns']['agent'])->firstWhere('key', '')['label'])->toBe(__('Unassigned'));
});

test('reports can be narrowed to a group', function () {
    $report = (new TicketReport(now()->subDays(6)->toImmutable(), now()->toImmutable(), groupId: $this->group->id))->build();

    expect($report['totals']['created'])->toBe(1)
        ->and($report['totals']['backlog'])->toBe(0)
        ->and($report['breakdowns']['group'])->toHaveCount(1);
});

test('the median of an even count averages the middle values', function () {
    expect(TicketReport::median([]))->toBeNull()
        ->and(TicketReport::median([5, 1, 3]))->toBe(3)
        ->and(TicketReport::median([4, 1, 3, 10]))->toBe(4);
});

test('agents and admins see reports; light agents and customers do not', function () {
    $this->actingAs($this->agent)
        ->get(route('agent.reports.index', ['range' => '7']))
        ->assertInertia(fn (Assert $page) => $page
            ->component('agent/reports/index')
            ->where('filters.range', '7')
            ->where('report.totals.created', 3));

    $this->actingAs(User::factory()->admin()->create())->get(route('agent.reports.index'))->assertOk();
    $this->actingAs(User::factory()->lightAgent()->create())->get(route('agent.reports.index'))->assertForbidden();
    $this->actingAs(User::factory()->create())->get(route('agent.reports.index'))->assertForbidden();
});

test('custom ranges are validated', function () {
    $this->actingAs($this->agent)
        ->get(route('agent.reports.index', ['range' => 'custom', 'from' => '2026-10-12', 'to' => '2026-10-01']))
        ->assertSessionHasErrors('to');
});

test('the report downloads as CSV', function () {
    $response = $this->actingAs($this->agent)->get(route('agent.reports.export', ['range' => '7']));

    $response->assertOk()->assertDownload('kitedesk-report-2026-10-06-2026-10-12.csv');
    $csv = $response->streamedContent();

    expect($csv)->toContain('created,3')
        ->and($csv)->toContain('2026-10-11,1,0')
        ->and($csv)->toContain('Ana,2,2,60,780');
});
