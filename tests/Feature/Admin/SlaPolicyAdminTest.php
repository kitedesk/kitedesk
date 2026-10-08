<?php

use App\Domain\Accounts\Models\Group;
use App\Domain\Sla\Models\BusinessSchedule;
use App\Domain\Sla\Models\SlaPolicy;
use App\Domain\Tickets\Actions\CreateTicket;
use App\Domain\Tickets\Enums\TicketChannel;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
});

/**
 * @return array<string, mixed>
 */
function policyPayload(array $overrides = []): array
{
    return array_replace_recursive([
        'name' => 'Enterprise',
        'description' => 'Key accounts',
        'business_schedule_id' => null,
        'is_active' => true,
        'conditions' => ['priorities' => ['urgent', 'high'], 'group_ids' => [], 'organization_ids' => []],
        'targets' => [
            'urgent' => ['first_response' => 30, 'next_reply' => 60, 'resolution' => 240],
            'high' => ['first_response' => 60, 'next_reply' => null, 'resolution' => 480],
            'normal' => ['first_response' => null, 'next_reply' => null, 'resolution' => null],
            'low' => ['first_response' => null, 'next_reply' => null, 'resolution' => null],
        ],
    ], $overrides);
}

test('non administrators cannot manage SLA policies', function () {
    $this->actingAs(User::factory()->agent()->create())
        ->get(route('admin.sla-policies.index'))
        ->assertForbidden();

    $this->actingAs(User::factory()->agent()->create())
        ->post(route('admin.sla-policies.store'), policyPayload())
        ->assertForbidden();
});

test('the policy list is ordered by position', function () {
    $second = SlaPolicy::factory()->create(['name' => 'Second', 'position' => 2]);
    $first = SlaPolicy::factory()->create(['name' => 'First', 'position' => 1]);

    $this->actingAs($this->admin)
        ->get(route('admin.sla-policies.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/sla/policies/index')
            ->where('policies.0.id', $first->id)
            ->where('policies.1.id', $second->id));
});

test('admins can create a policy with conditions and targets', function () {
    $group = Group::factory()->create();
    $schedule = BusinessSchedule::query()->create(['name' => 'Office', 'timezone' => 'UTC', 'hours' => [1 => [['start' => '09:00', 'end' => '17:00']]]]);
    SlaPolicy::factory()->create(['position' => 4]);

    $this->actingAs($this->admin)
        ->post(route('admin.sla-policies.store'), policyPayload([
            'business_schedule_id' => $schedule->id,
            'conditions' => ['group_ids' => [$group->id]],
        ]))
        ->assertRedirect(route('admin.sla-policies.index'));

    $policy = SlaPolicy::query()->where('name', 'Enterprise')->sole();

    expect($policy->business_schedule_id)->toBe($schedule->id)
        ->and($policy->position)->toBe(5)
        ->and($policy->conditions)->toBe(['priorities' => ['urgent', 'high'], 'group_ids' => [$group->id]])
        ->and($policy->targets['urgent'])->toBe(['first_response' => 30, 'next_reply' => 60, 'resolution' => 240])
        ->and($policy->targets['high']['next_reply'])->toBeNull()
        ->and($policy->targets['low'])->toBe(['first_response' => null, 'next_reply' => null, 'resolution' => null]);
});

test('empty conditions are stored as null so the policy matches any ticket', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.sla-policies.store'), [
            ...policyPayload(),
            'conditions' => ['priorities' => [], 'group_ids' => [], 'organization_ids' => []],
        ]);

    expect(SlaPolicy::query()->sole()->conditions)->toBeNull();
});

test('targets must be positive whole minutes and conditions must exist', function (array $overrides, string $error) {
    $this->actingAs($this->admin)
        ->post(route('admin.sla-policies.store'), policyPayload($overrides))
        ->assertSessionHasErrors($error);

    expect(SlaPolicy::query()->count())->toBe(0);
})->with([
    'negative target' => [['targets' => ['urgent' => ['first_response' => -5]]], 'targets.urgent.first_response'],
    'zero target' => [['targets' => ['high' => ['resolution' => 0]]], 'targets.high.resolution'],
    'fractional target' => [['targets' => ['normal' => ['next_reply' => 1.5]]], 'targets.normal.next_reply'],
    'unknown priority condition' => [['conditions' => ['priorities' => ['critical']]], 'conditions.priorities.0'],
    'missing group' => [['conditions' => ['group_ids' => [999]]], 'conditions.group_ids.0'],
    'missing schedule' => [['business_schedule_id' => 999], 'business_schedule_id'],
    'missing name' => [['name' => ''], 'name'],
]);

test('admins can update and delete a policy', function () {
    $policy = SlaPolicy::factory()->create();

    $this->actingAs($this->admin)
        ->put(route('admin.sla-policies.update', $policy), policyPayload(['name' => 'Renamed', 'is_active' => false]))
        ->assertRedirect(route('admin.sla-policies.index'));

    $policy->refresh();
    expect($policy->name)->toBe('Renamed')
        ->and($policy->is_active)->toBeFalse()
        ->and($policy->targets['urgent']['first_response'])->toBe(30);

    $this->actingAs($this->admin)
        ->delete(route('admin.sla-policies.destroy', $policy))
        ->assertRedirect(route('admin.sla-policies.index'));

    expect(SlaPolicy::query()->count())->toBe(0);
});

test('admins can toggle a policy', function () {
    $policy = SlaPolicy::factory()->create(['is_active' => true]);

    $this->actingAs($this->admin)->patch(route('admin.sla-policies.toggle', $policy))->assertRedirect();

    expect($policy->refresh()->is_active)->toBeFalse();
});

test('policies can be reordered', function () {
    $first = SlaPolicy::factory()->create(['position' => 0]);
    $second = SlaPolicy::factory()->create(['position' => 1]);
    $third = SlaPolicy::factory()->create(['position' => 2]);

    $this->actingAs($this->admin)
        ->patch(route('admin.sla-policies.move', $third), ['direction' => 'up'])
        ->assertRedirect();

    expect(SlaPolicy::query()->inEvaluationOrder()->pluck('id')->all())->toBe([$first->id, $third->id, $second->id]);

    $this->actingAs($this->admin)->patch(route('admin.sla-policies.move', $first), ['direction' => 'up']);
    expect(SlaPolicy::query()->inEvaluationOrder()->pluck('id')->all())->toBe([$first->id, $third->id, $second->id]);

    $this->actingAs($this->admin)
        ->patch(route('admin.sla-policies.move', $first), ['direction' => 'sideways'])
        ->assertSessionHasErrors('direction');
});

test('a newly created policy is applied to new tickets', function () {
    $this->travelTo('2026-10-12 10:00:00');

    $this->actingAs($this->admin)->post(route('admin.sla-policies.store'), [...policyPayload(), 'conditions' => null]);

    $ticket = app(CreateTicket::class)->handle(
        User::factory()->create(),
        ['subject' => 'Down', 'body' => '<p>Help</p>', 'priority' => 'urgent'],
        TicketChannel::Portal,
    );

    expect($ticket->slaPolicy?->name)->toBe('Enterprise')
        ->and($ticket->first_response_due_at?->toDateTimeString())->toBe('2026-10-12 10:30:00');
});
