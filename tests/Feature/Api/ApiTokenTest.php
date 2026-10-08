<?php

use App\Domain\Accounts\Enums\Permission;
use App\Domain\Accounts\Enums\TicketAccess;
use App\Domain\Accounts\Support\RoleCatalog;
use App\Domain\Entitlements\Contracts\Entitlements;
use App\Domain\Entitlements\Enums\Feature;
use App\Domain\Entitlements\Enums\Limit;
use App\Domain\Tickets\Models\Ticket;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\Sanctum;

test('staff create and revoke their own tokens in settings', function () {
    $agent = User::factory()->agent()->create();

    $this->actingAs($agent)
        ->post(route('api-tokens.store'), ['name' => 'My script', 'abilities' => ['tickets:read', 'reports:read'], 'expires_in_days' => 30])
        ->assertRedirect(route('api-tokens.index'))
        ->assertInertiaFlash('newToken.name', 'My script');

    $token = PersonalAccessToken::query()->sole();
    expect($token->tokenable_id)->toBe($agent->id)
        ->and($token->abilities)->toBe(['tickets:read', 'reports:read']);

    $someoneElses = User::factory()->agent()->create()->createToken('theirs', ['tickets:read'])->accessToken;

    $this->actingAs($agent)
        ->get(route('api-tokens.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/api-tokens')
            ->has('tokens', 1)
            ->where('tokens.0.id', $token->id)
            ->where('owner.abilities', fn ($abilities): bool => ! collect($abilities)->contains('users:write')));

    $this->actingAs($agent)->delete(route('api-tokens.destroy', $someoneElses))->assertNotFound();
    $this->actingAs($agent)->delete(route('api-tokens.destroy', $token))->assertRedirect();

    expect(PersonalAccessToken::query()->pluck('id')->all())->toBe([$someoneElses->id]);
});

test("a token's abilities are capped by its owner's role", function () {
    $this->actingAs(User::factory()->agent()->create())
        ->post(route('api-tokens.store'), ['name' => 'Too much', 'abilities' => ['tickets:read', 'users:write'], 'expires_in_days' => 30])
        ->assertSessionHasErrors('abilities.1');

    expect(PersonalAccessToken::query()->count())->toBe(0);
});

test('customers have no API tokens page', function () {
    $this->actingAs(User::factory()->create())->get(route('api-tokens.index'))->assertForbidden();
});

test('admins can only create tokens for people who cannot do more than them', function () {
    $integrations = User::factory()->withPermissions([Permission::ManageIntegrations, Permission::ViewReports])->create();
    $reporter = User::factory()->withPermissions([Permission::ViewReports])->create();
    $admin = User::factory()->admin()->create();
    $narrowAccess = User::factory()->withPermissions([Permission::ManageIntegrations], TicketAccess::Assigned)->create();

    $this->actingAs($integrations)
        ->get(route('admin.api-tokens.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('owners', fn ($owners): bool => collect($owners)->pluck('id')->sort()->values()->all() === [$integrations->id, $reporter->id, $narrowAccess->id]));

    $this->actingAs($integrations)
        ->post(route('admin.api-tokens.store'), ['name' => 'Borrowed', 'user_id' => $admin->id, 'abilities' => ['tickets:read'], 'expires_in_days' => 30])
        ->assertSessionHasErrors('user_id');

    $this->actingAs($narrowAccess)
        ->post(route('admin.api-tokens.store'), ['name' => 'Wider', 'user_id' => $reporter->id, 'abilities' => ['tickets:read'], 'expires_in_days' => 30])
        ->assertSessionHasErrors('user_id');

    $this->actingAs($integrations)
        ->post(route('admin.api-tokens.store'), ['name' => 'Reports', 'user_id' => $reporter->id, 'abilities' => ['reports:read'], 'expires_in_days' => 30])
        ->assertSessionHasNoErrors();

    expect(PersonalAccessToken::query()->sole()->tokenable_id)->toBe($reporter->id);
});

test('a token loses an ability when its owner loses the permission', function () {
    $owner = User::factory()->admin()->create();
    Sanctum::actingAs($owner, ['users:read', 'users:write']);

    $this->postJson(route('api.v1.users.store'), ['name' => 'First', 'email' => 'first@example.com'])->assertCreated();

    $owner->assignStaffRole(RoleCatalog::AGENT);

    $this->postJson(route('api.v1.users.store'), ['name' => 'Second', 'email' => 'second@example.com'])
        ->assertForbidden()
        ->assertJsonPath('message', "The token owner's role does not allow this.");
});

test('expired tokens, deactivated owners and plans without the API are refused', function () {
    $agent = User::factory()->agent()->create();
    $expired = $agent->createToken('old', ['tickets:read'], now()->subDay())->plainTextToken;
    $this->withToken($expired)->getJson(route('api.v1.tickets.index'))->assertUnauthorized();

    $token = $agent->createToken('current', ['tickets:read'])->plainTextToken;
    $agent->forceFill(['deactivated_at' => now()])->save();
    $this->withToken($token)->getJson(route('api.v1.tickets.index'))->assertForbidden();

    $this->app->instance(Entitlements::class, new class implements Entitlements
    {
        public function allows(Feature $feature): bool
        {
            return $feature !== Feature::Api;
        }

        public function limit(Limit $limit): ?int
        {
            return null;
        }
    });

    Sanctum::actingAs(User::factory()->agent()->create(), ['tickets:read']);
    $this->getJson(route('api.v1.tickets.index'))->assertForbidden();
});

test('lists only return tickets the token owner can see', function () {
    $agent = User::factory()->withPermissions([Permission::UpdateTickets], TicketAccess::Assigned)->create();
    $mine = Ticket::factory()->create(['assignee_id' => $agent->id]);
    Ticket::factory()->create();
    Sanctum::actingAs($agent, ['tickets:read']);

    $this->getJson(route('api.v1.tickets.index'))->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $mine->id);
});
