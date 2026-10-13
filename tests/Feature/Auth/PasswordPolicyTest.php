<?php

use App\Domain\Accounts\Support\PasswordPolicy;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;

function savePolicy(array $policy = []): void
{
    (new PasswordPolicy(...$policy))->save();
}

function changePassword(User $user, string $password): TestResponse
{
    return test()->actingAs($user)->put(route('user-password.update'), [
        'current_password' => 'password',
        'password' => $password,
        'password_confirmation' => $password,
    ]);
}

test('until a policy is saved, passwords follow the rules from before', function () {
    $user = User::factory()->create();

    changePassword($user, 'short')->assertSessionHasErrors('password');
    changePassword($user, 'eight chars')->assertSessionHasNoErrors();
});

test('the saved policy decides what a new password must contain, wherever it is chosen', function () {
    savePolicy(['minLength' => 12, 'numbers' => true]);
    $user = User::factory()->create();

    $this->post(route('password.update'), [
        'token' => Password::broker()->createToken($user),
        'email' => $user->email,
        'password' => 'short1',
        'password_confirmation' => 'short1',
    ])->assertSessionHasErrors('password');

    changePassword($user, 'longbutnonumbers')->assertSessionHasErrors('password');
    changePassword($user, 'long enough with 1 number')->assertSessionHasNoErrors();

    expect(Hash::check('long enough with 1 number', $user->fresh()->password))->toBeTrue()
        ->and($user->fresh()->password_changed_at)->not->toBeNull();
});

test('recent passwords cannot be chosen again, older ones can', function () {
    savePolicy(['rememberLast' => 2]);
    $user = User::factory()->create();
    $change = fn (string $current, string $new) => $this->actingAs($user)->put(route('user-password.update'), [
        'current_password' => $current,
        'password' => $new,
        'password_confirmation' => $new,
    ]);

    $change('password', 'password')->assertSessionHasErrors(['password' => 'Choose a password you haven’t used recently.']);
    $change('password', 'first change')->assertSessionHasNoErrors();
    $change('first change', 'second change')->assertSessionHasNoErrors();

    $change('second change', 'first change')->assertSessionHasErrors('password');
    $change('second change', 'password')->assertSessionHasNoErrors();
});

test('signing in does not restart the expiry clock', function () {
    $user = User::factory()->create(['password_changed_at' => now()->subDays(10)]);

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password']);

    expect($user->fresh()->password_changed_at->toDateString())->toBe(now()->subDays(10)->toDateString())
        ->and($user->passwordHistories()->count())->toBe(0);
});

test('people whose password expired or who were asked for a new one must change it first', function (array $state) {
    savePolicy(['expiresAfterDays' => 90]);
    $user = User::factory()->agent()->create(['password_changed_at' => now(), ...$state]);

    $this->actingAs($user)->get(route('dashboard'))->assertRedirect(route('password.change'));
    $this->actingAs($user)->get('/admin')->assertRedirect(route('password.change'));
    $this->actingAs($user)->getJson(route('dashboard'))->assertForbidden();

    $this->actingAs($user)->get(route('password.change'))
        ->assertInertia(fn (Assert $page) => $page->component('auth/change-password'));
    $this->actingAs($user)->post(route('logout'))->assertRedirect();
})->with([
    'expired' => [['password_changed_at' => now()->subDays(91)]],
    'asked by an admin' => [['password_change_required' => true]],
]);

test('choosing a new password lets them through', function () {
    $user = User::factory()->create(['password_change_required' => true]);

    $this->actingAs($user)->put(route('password.change.update'), [
        'current_password' => 'wrong',
        'password' => 'a brand new one',
        'password_confirmation' => 'a brand new one',
    ])->assertSessionHasErrors('current_password');

    $this->actingAs($user)->put(route('password.change.update'), [
        'current_password' => 'password',
        'password' => 'a brand new one',
        'password_confirmation' => 'a brand new one',
    ])->assertRedirect(route('dashboard'));

    expect($user->fresh()->password_change_required)->toBeFalse();
    $this->actingAs($user->fresh())->get(route('profile.edit'))->assertOk();
});

test('staff without two-factor authentication must turn it on when the policy asks', function () {
    savePolicy(['requireTwoFactorForStaff' => true]);
    $agent = User::factory()->agent()->create();
    $customer = User::factory()->create();

    $this->actingAs($agent)->get(route('dashboard'))->assertRedirect(route('security.edit'));
    $this->actingAs($agent)->withSession(['auth.password_confirmed_at' => time()])->get(route('security.edit'))
        ->assertInertia(fn (Assert $page) => $page->where('twoFactorRequired', true));
    $this->actingAs($customer)->get(route('profile.edit'))->assertOk();

    $agent->forceFill(['two_factor_secret' => encrypt('secret'), 'two_factor_confirmed_at' => now()])->save();
    $this->actingAs($agent->fresh())->get(route('profile.edit'))->assertOk();
});

test('a passkey counts as two-factor authentication', function () {
    savePolicy(['requireTwoFactorForStaff' => true]);
    $agent = User::factory()->agent()->create();
    $agent->passkeys()->create(['name' => 'Laptop', 'credential_id' => 'abc', 'credential' => []]);

    $this->actingAs($agent)->get(route('profile.edit'))->assertOk();
});

test('admins set the policy on the Security page', function () {
    $admin = User::factory()->admin()->create();
    $agent = User::factory()->agent()->create();

    $this->actingAs($agent)->get(route('admin.security.edit'))->assertForbidden();

    $this->actingAs($admin)->put(route('admin.security.update'), [
        'min_length' => 14, 'mixed_case' => true, 'numbers' => true, 'symbols' => false, 'uncompromised' => false,
        'expires_after_days' => 90, 'remember_last' => 5, 'require_two_factor_for_staff' => false,
    ])->assertSessionHasNoErrors();

    expect(PasswordPolicy::current())->toEqual(new PasswordPolicy(14, true, true, false, false, 90, 5, false));

    $this->actingAs($admin)->get(route('admin.security.edit'))
        ->assertInertia(fn (Assert $page) => $page->component('admin/security/edit')->where('policy.min_length', 14));
});
