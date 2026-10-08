<?php

use App\Domain\Accounts\Models\Group;
use App\Domain\Accounts\Models\Organization;
use App\Domain\Accounts\Models\Role;
use App\Domain\Accounts\Support\RoleCatalog;
use App\Domain\Entitlements\Contracts\Entitlements;
use App\Domain\Entitlements\Enums\Feature;
use App\Domain\Entitlements\Enums\Limit;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    Sanctum::actingAs($this->admin, ['users:read', 'users:write']);
});

test('staff can be created with a role and groups, and are invited', function () {
    Notification::fake();
    $group = Group::factory()->create();
    $role = Role::query()->where('name', RoleCatalog::AGENT)->sole();

    $this->getJson(route('api.v1.roles.index'))->assertOk()->assertJsonFragment(['id' => $role->id]);

    $this->postJson(route('api.v1.users.store'), ['name' => 'Ada', 'email' => 'ada@example.com', 'type' => 'staff', 'role_id' => $role->id, 'group_ids' => [$group->id]])
        ->assertCreated()
        ->assertJsonPath('data.role.id', $role->id)
        ->assertJsonPath('data.groups.0.id', $group->id);

    Notification::assertSentTo(User::query()->where('email', 'ada@example.com')->sole(), ResetPassword::class);
});

test('customers can be imported without an invitation', function () {
    Notification::fake();

    $this->postJson(route('api.v1.users.store'), ['name' => 'Bo', 'email' => 'bo@example.com', 'invite' => false])
        ->assertCreated()
        ->assertJsonPath('data.type', 'customer');

    Notification::assertNothingSent();
});

test('staff need a free seat', function () {
    $this->app->instance(Entitlements::class, new class implements Entitlements
    {
        public function allows(Feature $feature): bool
        {
            return true;
        }

        public function limit(Limit $limit): ?int
        {
            return $limit === Limit::AgentSeats ? 1 : null;
        }
    });

    $this->postJson(route('api.v1.users.store'), ['name' => 'Ada', 'email' => 'ada@example.com', 'type' => 'staff', 'role_id' => Role::query()->first()->id])
        ->assertJsonValidationErrors('role_id');
});

test('users can be updated, deactivated and reactivated', function () {
    $customer = User::factory()->create(['name' => 'Old']);
    $organization = Organization::factory()->create();

    $this->putJson(route('api.v1.users.update', $customer), ['name' => 'New', 'email' => $customer->email, 'organization_id' => $organization->id])
        ->assertOk()
        ->assertJsonPath('data.name', 'New')
        ->assertJsonPath('data.organization.id', $organization->id);

    $this->postJson(route('api.v1.users.deactivate', $customer))->assertOk()->assertJsonPath('data.deactivated_at', fn ($value) => $value !== null);
    $this->postJson(route('api.v1.users.reactivate', $customer))->assertOk()->assertJsonPath('data.deactivated_at', null);
    $this->postJson(route('api.v1.users.deactivate', $this->admin))->assertJsonValidationErrors('user');
});

test('organizations and groups can be managed', function () {
    $organization = $this->postJson(route('api.v1.organizations.store'), ['name' => 'Acme', 'domains' => ['acme.test']])
        ->assertCreated()
        ->assertJsonPath('data.domains', ['acme.test'])
        ->json('data.id');

    $this->putJson(route('api.v1.organizations.update', $organization), ['name' => 'Acme Inc'])->assertOk()->assertJsonPath('data.name', 'Acme Inc');
    $this->deleteJson(route('api.v1.organizations.destroy', $organization))->assertNoContent();

    $agent = User::factory()->agent()->create();
    $group = $this->postJson(route('api.v1.groups.store'), ['name' => 'Billing', 'agent_ids' => [$agent->id]])
        ->assertCreated()
        ->assertJsonPath('data.agent_ids', [$agent->id])
        ->json('data.id');

    $this->putJson(route('api.v1.groups.update', $group), ['name' => 'Billing team'])->assertOk()->assertJsonPath('data.agent_ids', [$agent->id]);
    $this->deleteJson(route('api.v1.groups.destroy', $group))->assertNoContent();
});
