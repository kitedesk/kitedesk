<?php

use App\Domain\Accounts\Models\Group;
use App\Domain\Tickets\Models\RoutingRule;
use App\Domain\Tickets\Models\TicketCategory;
use App\Domain\Tickets\Models\TicketField;
use App\Domain\Tickets\Models\TicketForm;
use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
});

test('admins build forms from ordered fields, with a single default form', function () {
    [$first, $second] = TicketField::factory()->count(2)->create()->all();
    $previousDefault = TicketForm::factory()->default()->create();

    $this->actingAs($this->admin)->post(route('admin.ticket-forms.store'), [
        'name' => 'Billing',
        'description' => null,
        'is_default' => true,
        'fields' => [
            ['id' => $second->id, 'is_required' => true],
            ['id' => $first->id, 'is_required' => false],
        ],
    ])->assertRedirect(route('admin.ticket-forms.index'));

    $form = TicketForm::query()->latest('id')->firstOrFail();
    expect($form->name)->toBe('Billing')
        ->and($form->description)->toBeNull()
        ->and($form->fields->pluck('id')->all())->toBe([$second->id, $first->id])
        ->and($form->fields->first()?->pivot?->is_required)->toBeTrue()
        ->and($form->is_default)->toBeTrue()
        ->and($previousDefault->refresh()->is_default)->toBeFalse();
});

test('categories are at most two levels deep', function () {
    $this->actingAs($this->admin)->post(route('admin.ticket-categories.store'), [
        'name' => 'Billing',
    ])->assertSessionHasNoErrors();
    $billing = TicketCategory::query()->sole();

    $this->actingAs($this->admin)->post(route('admin.ticket-categories.store'), [
        'name' => 'Refunds',
        'parent_id' => $billing->id,
    ])->assertSessionHasNoErrors();
    $refunds = TicketCategory::query()->where('parent_id', $billing->id)->sole();

    $this->actingAs($this->admin)
        ->post(route('admin.ticket-categories.store'), ['name' => 'Partial refunds', 'parent_id' => $refunds->id])
        ->assertSessionHasErrors('parent_id');

    $other = TicketCategory::factory()->create();
    $this->actingAs($this->admin)
        ->put(route('admin.ticket-categories.update', $billing), ['name' => 'Billing', 'parent_id' => $other->id])
        ->assertSessionHasErrors('parent_id');
});

test('routing rules are validated, ordered and can be paused', function () {
    $group = Group::factory()->create();
    TicketField::factory()->create(['key' => 'plan']);

    $this->actingAs($this->admin)->post(route('admin.routing-rules.store'), [
        'name' => 'Enterprise',
        'match' => 'all',
        'conditions' => [['field' => 'custom_fields.plan', 'operator' => 'is', 'value' => 'Enterprise']],
        'actions' => ['group_id' => $group->id, 'priority' => 'high', 'tags' => ['Key Account']],
    ])->assertRedirect(route('admin.routing-rules.index'));

    $rule = RoutingRule::query()->sole();
    expect($rule->conditions)->toBe([['field' => 'custom_fields.plan', 'operator' => 'is', 'value' => 'Enterprise']])
        ->and($rule->actions)->toBe(['group_id' => $group->id, 'priority' => 'high', 'tags' => ['key_account']]);

    $this->actingAs($this->admin)->post(route('admin.routing-rules.store'), [
        'name' => 'Broken',
        'match' => 'all',
        'conditions' => [['field' => 'custom_fields.missing', 'operator' => 'is', 'value' => 'x']],
        'actions' => [],
    ])->assertSessionHasErrors(['conditions.0.field', 'actions.group_id']);

    $second = RoutingRule::factory()->create(['position' => 1]);
    $this->actingAs($this->admin)->post(route('admin.routing-rules.move', $second), ['direction' => 'up']);
    expect(RoutingRule::query()->ordered()->pluck('id')->all())->toBe([$second->id, $rule->id]);

    $this->actingAs($this->admin)->patch(route('admin.routing-rules.toggle', $rule));
    expect($rule->refresh()->is_active)->toBeFalse();
});

test('only admins manage forms, categories and routing rules', function () {
    $agent = User::factory()->agent()->create();

    $this->actingAs($agent)->get(route('admin.ticket-forms.index'))->assertForbidden();
    $this->actingAs($agent)->post(route('admin.ticket-categories.store'), ['name' => 'X'])->assertForbidden();
    $this->actingAs($agent)->get(route('admin.routing-rules.create'))->assertForbidden();
});

test('the admin pages render', function (string $route) {
    TicketForm::factory()->default()->create();
    TicketCategory::factory()->create();
    RoutingRule::factory()->create();

    $this->actingAs($this->admin)->get(route($route))->assertOk();
})->with(['admin.ticket-forms.index', 'admin.ticket-forms.create', 'admin.ticket-categories.index', 'admin.routing-rules.index', 'admin.routing-rules.create', 'admin.ticket-fields.index']);
