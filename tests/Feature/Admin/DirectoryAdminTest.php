<?php

use App\Domain\Accounts\Enums\Permission;
use App\Domain\Accounts\Models\Group;
use App\Domain\Accounts\Models\Organization;
use App\Domain\Accounts\Models\Role;
use App\Domain\Accounts\Support\RoleCatalog;
use App\Domain\Tickets\Models\Ticket;
use App\Domain\Tickets\Models\TicketField;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
});

test('admins can invite a team member who receives a password link', function () {
    Notification::fake();
    $group = Group::factory()->create();

    $this->actingAs($this->admin)->post(route('admin.users.store'), [
        'name' => 'New Agent',
        'email' => 'NEW@example.com',
        'type' => 'staff',
        'role_id' => Role::findByName(RoleCatalog::AGENT)->id,
        'group_ids' => [$group->id],
    ])->assertRedirect();

    $agent = User::query()->where('email', 'new@example.com')->sole();
    expect($agent->isStaff())->toBeTrue()
        ->and($agent->hasRole(RoleCatalog::AGENT))->toBeTrue()
        ->and($agent->groups->pluck('id')->all())->toBe([$group->id]);
    Notification::assertSentTo($agent, ResetPassword::class);
    $this->assertDatabaseHas('activity_log', ['subject_id' => $agent->id, 'event' => 'created', 'causer_id' => $this->admin->id]);
});

test('admins can change roles but not demote themselves', function () {
    $agent = User::factory()->agent()->create();
    $lightAgent = Role::findByName(RoleCatalog::LIGHT_AGENT);

    $this->actingAs($this->admin)
        ->put(route('admin.users.update', $agent), ['name' => $agent->name, 'email' => $agent->email, 'type' => 'staff', 'role_id' => $lightAgent->id])
        ->assertRedirect()
        ->assertSessionHasNoErrors();
    expect($agent->refresh()->roles->pluck('name')->all())->toBe([RoleCatalog::LIGHT_AGENT]);

    $this->actingAs($this->admin)
        ->put(route('admin.users.update', $agent), ['name' => $agent->name, 'email' => $agent->email, 'type' => 'customer'])
        ->assertSessionHasNoErrors();
    expect($agent->refresh())->isStaff()->toBeFalse()->roles->toBeEmpty();

    $this->actingAs($this->admin)
        ->put(route('admin.users.update', $this->admin), ['name' => 'Me', 'email' => $this->admin->email, 'type' => 'staff', 'role_id' => $lightAgent->id])
        ->assertSessionHasErrors('role_id');
});

test('the last administrator cannot be demoted or deleted', function () {
    $teamLead = User::factory()->withPermissions([Permission::ManageTeam])->create();
    $agentRole = Role::findByName(RoleCatalog::AGENT);

    $this->actingAs($teamLead)
        ->put(route('admin.users.update', $this->admin), ['name' => 'Admin', 'email' => $this->admin->email, 'type' => 'staff', 'role_id' => $agentRole->id])
        ->assertSessionHasErrors('role_id');
    $this->actingAs($teamLead)->delete(route('admin.users.destroy', $this->admin))->assertStatus(422);

    $otherAdmin = User::factory()->admin()->create();
    $this->actingAs($teamLead)
        ->put(route('admin.users.update', $this->admin), ['name' => 'Admin', 'email' => $this->admin->email, 'type' => 'staff', 'role_id' => $agentRole->id])
        ->assertSessionHasNoErrors();
    expect($this->admin->refresh()->isAdmin())->toBeFalse()
        ->and($otherAdmin->isAdmin())->toBeTrue();
});

test('the people list separates staff from customers', function () {
    User::factory()->agent()->create();
    User::factory()->count(2)->create();

    $this->actingAs($this->admin)
        ->get(route('admin.users.index', ['audience' => 'customers']))
        ->assertInertia(fn (Assert $page) => $page->component('admin/users/index')->has('users.data', 2));
});

test('admins cannot delete themselves', function () {
    $this->actingAs($this->admin)->delete(route('admin.users.destroy', $this->admin))->assertStatus(422);
});

test('groups can be managed with their agents', function () {
    $agent = User::factory()->agent()->create();
    $customer = User::factory()->create();

    $this->actingAs($this->admin)
        ->post(route('admin.groups.store'), ['name' => 'Tier 2', 'agent_ids' => [$customer->id]])
        ->assertSessionHasErrors('agent_ids.0');

    $this->actingAs($this->admin)->post(route('admin.groups.store'), ['name' => 'Tier 2', 'agent_ids' => [$agent->id]]);

    $group = Group::query()->where('name', 'Tier 2')->sole();
    expect($group->agents->pluck('id')->all())->toBe([$agent->id]);

    $this->actingAs($this->admin)->delete(route('admin.groups.destroy', $group));
    $this->assertModelMissing($group);
});

test('organization domains are normalized and validated', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.organizations.store'), ['name' => 'Initech', 'domains' => 'Initech.com, initech.io  initech.com'])
        ->assertSessionHasNoErrors();

    expect(Organization::query()->sole()->domains)->toBe(['initech.com', 'initech.io']);

    $this->actingAs($this->admin)
        ->post(route('admin.organizations.store'), ['name' => 'Bad', 'domains' => 'not a domain!'])
        ->assertSessionHasErrors();
});

test('ticket fields can be created and reordered', function () {
    $this->actingAs($this->admin)->post(route('admin.ticket-fields.store'), [
        'label' => 'Product area',
        'type' => 'select',
        'options' => ['Billing', ' Mobile ', '', 'Billing'],
        'is_visible_to_customers' => true,
    ])->assertSessionHasNoErrors();
    $this->actingAs($this->admin)->post(route('admin.ticket-fields.store'), ['label' => 'Order number', 'type' => 'text']);

    $first = TicketField::query()->where('key', 'product_area')->sole();
    expect($first->options)->toBe(['Billing', 'Mobile']);

    $this->actingAs($this->admin)->post(route('admin.ticket-fields.move', $first), ['direction' => 'down']);
    expect(TicketField::query()->ordered()->pluck('key')->all())->toBe(['order_number', 'product_area']);
});

test('the type of an existing ticket field cannot change', function () {
    $field = TicketField::factory()->create(['key' => 'plan']);

    $this->actingAs($this->admin)
        ->put(route('admin.ticket-fields.update', $field), ['label' => 'Plan', 'key' => 'plan', 'type' => 'number'])
        ->assertSessionHasErrors('type');
});

test('non-admins cannot manage the directory', function () {
    $agent = User::factory()->agent()->create();

    $this->actingAs($agent)->post(route('admin.groups.store'), ['name' => 'Sneaky'])->assertForbidden();
    $this->actingAs($agent)->put(route('admin.users.update', $agent), ['name' => 'x', 'email' => $agent->email, 'type' => 'staff', 'role_id' => RoleCatalog::administrator()->id])->assertForbidden();
    expect(Ticket::query()->count())->toBe(0);
});
