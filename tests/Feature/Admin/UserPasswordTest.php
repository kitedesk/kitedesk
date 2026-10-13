<?php

use App\Domain\Accounts\Notifications\PasswordSetByAdministrator;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->agent = User::factory()->agent()->create();
});

test('admins set a password and the person must replace it at next sign-in', function () {
    Notification::fake();
    $rememberToken = $this->agent->remember_token;

    $this->actingAs($this->admin)->put(route('admin.users.password.update', $this->agent), [
        'password' => 'handed over by phone',
        'password_confirmation' => 'handed over by phone',
        'require_change' => true,
        'notify' => false,
    ])->assertSessionHasNoErrors();

    $agent = $this->agent->fresh();
    expect(Hash::check('handed over by phone', $agent->password))->toBeTrue()
        ->and($agent->password_change_required)->toBeTrue()
        ->and($agent->remember_token)->not->toBe($rememberToken);
    Notification::assertNothingSent();
    $this->assertDatabaseHas('activity_log', ['subject_id' => $agent->id, 'event' => 'password_set', 'causer_id' => $this->admin->id, 'log_name' => 'users']);

    $this->actingAs($agent)->get(route('dashboard'))->assertRedirect(route('password.change'));
});

test('admins can email the person, without the password, and skip the forced change', function () {
    Notification::fake();

    $this->actingAs($this->admin)->put(route('admin.users.password.update', $this->agent), [
        'password' => 'handed over by phone',
        'password_confirmation' => 'handed over by phone',
        'require_change' => false,
        'notify' => true,
    ])->assertSessionHasNoErrors();

    expect($this->agent->fresh()->password_change_required)->toBeFalse();
    Notification::assertSentTo($this->agent, PasswordSetByAdministrator::class, function (PasswordSetByAdministrator $notification) {
        return ! str_contains(implode(' ', $notification->toMail($this->agent)->introLines), 'handed over by phone');
    });
});

test('admins can ask for a new password without setting one', function () {
    $this->actingAs($this->admin)->post(route('admin.users.password.require-change', $this->agent))->assertRedirect();

    expect($this->agent->fresh()->password_change_required)->toBeTrue();
});

test('setting passwords follows the policy and is for other people and admins only', function () {
    $this->actingAs($this->admin)->put(route('admin.users.password.update', $this->agent), [
        'password' => 'short', 'password_confirmation' => 'short', 'require_change' => true, 'notify' => false,
    ])->assertSessionHasErrors('password');

    $this->actingAs($this->admin)->put(route('admin.users.password.update', $this->admin), [
        'password' => 'a long password', 'password_confirmation' => 'a long password', 'require_change' => false, 'notify' => false,
    ])->assertStatus(422);

    $this->actingAs($this->agent)->post(route('admin.users.password.require-change', $this->admin))->assertForbidden();
});
