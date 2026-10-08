<?php

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('profile page is displayed', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->get(route('profile.edit'));

    $response->assertOk();
});

test('profile information can be updated', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    $user->refresh();

    expect($user->name)->toBe('Test User');
    expect($user->email)->toBe('test@example.com');
    expect($user->email_verified_at)->toBeNull();
});

test('email verification status is unchanged when the email address is unchanged', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => 'Test User',
            'email' => $user->email,
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    expect($user->refresh()->email_verified_at)->not->toBeNull();
});

test('user can delete their account', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->delete(route('profile.destroy'), [
            'password' => 'password',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('home'));

    $this->assertGuest();
    expect($user->fresh())->toBeNull();
});

test('correct password must be provided to delete account', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->from(route('profile.edit'))
        ->delete(route('profile.destroy'), [
            'password' => 'wrong-password',
        ]);

    $response
        ->assertSessionHasErrors('password')
        ->assertRedirect(route('profile.edit'));

    expect($user->fresh())->not->toBeNull();
});

test('people can set their details, language and timezone', function () {
    $user = User::factory()->agent()->create();

    $this->actingAs($user)->patch(route('profile.update'), [
        'name' => $user->name,
        'email' => $user->email,
        'job_title' => 'Support lead',
        'phone' => '+55 11 99999-0000',
        'locale' => 'pt_BR',
        'timezone' => 'America/Sao_Paulo',
        'signature' => "Ana\nSupport lead",
    ])->assertSessionHasNoErrors();

    expect($user->refresh()->only(['job_title', 'phone', 'locale', 'timezone', 'signature']))->toBe([
        'job_title' => 'Support lead',
        'phone' => '+55 11 99999-0000',
        'locale' => 'pt_BR',
        'timezone' => 'America/Sao_Paulo',
        'signature' => "Ana\nSupport lead",
    ]);
});

test('unknown languages and timezones are refused, and customers have no signature', function () {
    $customer = User::factory()->create();

    $this->actingAs($customer)->patch(route('profile.update'), [
        'name' => $customer->name,
        'email' => $customer->email,
        'locale' => 'xx',
        'timezone' => 'Mars/Olympus',
        'signature' => 'Hi',
    ])->assertSessionHasErrors(['locale', 'timezone', 'signature']);
});

test('people can upload and remove a profile photo, which anyone can load by its link', function () {
    Storage::fake('local');
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('profile.avatar.update'), ['avatar' => UploadedFile::fake()->image('me.png', 200, 200)])
        ->assertSessionHasNoErrors();

    $path = $user->refresh()->avatar_path;
    Storage::disk('local')->assertExists($path);
    expect($user->avatar)->toBe(route('avatars.show', basename($path)));

    auth()->logout();
    $this->get($user->avatar)->assertOk();
    $this->get(route('avatars.show', 'unknown.png'))->assertNotFound();

    $this->actingAs($user)->delete(route('profile.avatar.destroy'));

    Storage::disk('local')->assertMissing($path);
    expect($user->refresh()->avatar)->toBeNull();
});

test('profile photos must be images', function () {
    Storage::fake('local');
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('profile.avatar.update'), ['avatar' => UploadedFile::fake()->create('photo.svg', 10, 'image/svg+xml')])
        ->assertSessionHasErrors('avatar');

    expect($user->refresh()->avatar_path)->toBeNull();
});
