<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Sanctum\PersonalAccessToken;

test('non-admins cannot manage API tokens', function () {
    $this->actingAs(User::factory()->agent()->create())->get(route('admin.api-tokens.index'))->assertForbidden();
});

test('admins can create a token for a staff member and see it once', function () {
    $admin = User::factory()->admin()->create();
    $agent = User::factory()->agent()->create();

    $this->actingAs($admin)
        ->post(route('admin.api-tokens.store'), ['name' => 'CRM sync', 'user_id' => $agent->id, 'abilities' => ['tickets:read', 'tickets:write'], 'expires_in_days' => 90])
        ->assertRedirect(route('admin.api-tokens.index'))
        ->assertInertiaFlash('newToken.name', 'CRM sync');

    $token = PersonalAccessToken::query()->sole();
    expect($token->tokenable_id)->toBe($agent->id)
        ->and($token->abilities)->toBe(['tickets:read', 'tickets:write'])
        ->and($token->expires_at?->isSameDay(now()->addDays(90)))->toBeTrue()
        ->and($token->getAttribute('created_by_id'))->toBe($admin->id);

    $this->actingAs($admin)
        ->get(route('admin.api-tokens.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/api-tokens/index')
            ->has('tokens', 1)
            ->where('tokens.0.owner.id', $agent->id)
            ->where('tokens.0.created_by.id', $admin->id));
});

test('tokens cannot be issued to customers, with unknown abilities or an unlisted lifetime', function () {
    $admin = User::factory()->admin()->create();
    $agent = User::factory()->agent()->create();

    $this->actingAs($admin)
        ->post(route('admin.api-tokens.store'), ['name' => 'Bad', 'user_id' => $agent->id, 'abilities' => ['tickets:read'], 'expires_in_days' => 3650])
        ->assertSessionHasErrors('expires_in_days');

    $this->actingAs($admin)
        ->post(route('admin.api-tokens.store'), ['name' => 'Bad', 'user_id' => User::factory()->create()->id, 'abilities' => ['tickets:read'], 'expires_in_days' => 30])
        ->assertSessionHasErrors('user_id');

    $this->actingAs($admin)
        ->post(route('admin.api-tokens.store'), ['name' => 'Bad', 'user_id' => $agent->id, 'abilities' => ['tickets:read', 'root'], 'expires_in_days' => 30])
        ->assertSessionHasErrors('abilities.1');

    expect(PersonalAccessToken::query()->count())->toBe(0);
});

test('admins can revoke tokens', function () {
    $token = User::factory()->agent()->create()->createToken('old', ['tickets:read']);

    $this->actingAs(User::factory()->admin()->create())
        ->delete(route('admin.api-tokens.destroy', $token->accessToken))
        ->assertRedirect();

    expect(PersonalAccessToken::query()->count())->toBe(0);
});

test('the API reference is open to staff only', function () {
    $this->actingAs(User::factory()->create())->get('/docs/api')->assertForbidden();
    $this->actingAs(User::factory()->agent()->create())->get('/docs/api')->assertOk();
});
