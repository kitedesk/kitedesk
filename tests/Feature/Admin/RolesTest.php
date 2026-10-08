<?php

use App\Domain\Accounts\Enums\Permission;
use App\Domain\Accounts\Models\Role;
use App\Domain\Accounts\Support\RoleCatalog;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
});

test('the built-in roles keep the access admins, agents and light agents had', function () {
    expect(RoleCatalog::administrator()->permissions->pluck('name')->sort()->values()->all())->toBe(collect(Permission::values())->sort()->values()->all())
        ->and(Role::findByName(RoleCatalog::AGENT)->hasPermissionTo(Permission::ReplyToTickets->value))->toBeTrue()
        ->and(Role::findByName(RoleCatalog::AGENT)->hasPermissionTo(Permission::ManageTeam->value))->toBeFalse()
        ->and(Role::findByName(RoleCatalog::LIGHT_AGENT)->permissions)->toBeEmpty();
});

test('admins create, edit and delete a role', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.roles.store'), [
            'name' => 'Tier 1',
            'description' => 'First line',
            'ticket_access' => 'groups',
            'permissions' => [Permission::ReplyToTickets->value, Permission::ViewReports->value],
        ])
        ->assertRedirect(route('admin.roles.index'))
        ->assertSessionHasNoErrors();

    $role = Role::findByName('Tier 1');
    expect($role->ticket_access->value)->toBe('groups')
        ->and($role->permissions->pluck('name')->sort()->values()->all())->toBe(['reports.view', 'tickets.reply']);

    $this->actingAs($this->admin)
        ->put(route('admin.roles.update', $role), ['name' => 'Tier one', 'ticket_access' => 'all', 'permissions' => []])
        ->assertSessionHasNoErrors();
    expect($role->refresh())->name->toBe('Tier one')->permissions->toBeEmpty();

    $this->actingAs($this->admin)->get(route('admin.roles.index'))
        ->assertInertia(fn (Assert $page) => $page->component('admin/roles/index')->has('roles', 4));

    $this->actingAs($this->admin)->delete(route('admin.roles.destroy', $role))->assertRedirect();
    expect(Role::query()->whereKey($role->id)->exists())->toBeFalse();
});

test('unknown permissions are rejected', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.roles.store'), ['name' => 'Odd', 'ticket_access' => 'all', 'permissions' => ['tickets.everything']])
        ->assertSessionHasErrors('permissions.0');
});

test('the administrator role is locked and built-in roles or roles in use cannot be deleted', function () {
    $this->actingAs($this->admin)
        ->put(route('admin.roles.update', RoleCatalog::administrator()), ['name' => 'Boss', 'ticket_access' => 'assigned', 'permissions' => []])
        ->assertForbidden();

    $this->actingAs($this->admin)->delete(route('admin.roles.destroy', Role::findByName(RoleCatalog::AGENT)));
    expect(Role::findByName(RoleCatalog::AGENT)->exists)->toBeTrue();

    $inUse = User::factory()->withPermissions([Permission::ViewReports])->create()->staffRole();
    $this->actingAs($this->admin)->delete(route('admin.roles.destroy', $inUse));
    expect($inUse->fresh())->not->toBeNull();
});

test('members cannot take the team permission away from their own role', function () {
    $lead = User::factory()->withPermissions([Permission::ManageTeam])->create();

    $this->actingAs($lead)
        ->put(route('admin.roles.update', $lead->staffRole()), ['name' => 'Lead', 'ticket_access' => 'all', 'permissions' => []])
        ->assertSessionHasErrors('permissions');
});

test('admin sections open only for roles granting them', function () {
    $helpCenterEditor = User::factory()->withPermissions([Permission::ManageHelpCenter])->create();

    $this->actingAs($helpCenterEditor)->get(route('admin.index'))->assertOk();
    $this->actingAs($helpCenterEditor)->get(route('admin.knowledge-base.index'))->assertOk();
    $this->actingAs($helpCenterEditor)->get(route('admin.mailboxes.index'))->assertForbidden();
    $this->actingAs($helpCenterEditor)->get(route('admin.roles.index'))->assertForbidden();

    $this->actingAs(User::factory()->agent()->create())->get(route('admin.index'))->assertForbidden();
    $this->actingAs(User::factory()->create())->get(route('admin.index'))->assertForbidden();
});
