<?php

use App\Domain\Branding\Branding;
use App\Domain\Support\Installation;
use App\Models\User;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * @param  array<string, mixed>  $data
 */
function completeSetup(array $data = []): TestResponse
{
    return test()->post(route('setup.store'), [
        'code' => Installation::setupCode(),
        'helpdesk_name' => 'Larkspur Support',
        'name' => 'Ana Ribeiro',
        'email' => 'Ana@Example.com',
        'password' => 'a-Long-passw0rd!',
        'password_confirmation' => 'a-Long-passw0rd!',
        'timezone' => 'America/Sao_Paulo',
        ...$data,
    ]);
}

test('a new installation sends visitors to the setup screen', function () {
    $this->get('/')->assertRedirect(route('setup.show'));
    $this->get(route('login'))->assertRedirect(route('setup.show'));

    $this->get(route('setup.show', ['code' => 'abc']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('auth/setup')->where('code', 'abc'));
});

test('the setup screen names the helpdesk and signs the first administrator in', function () {
    completeSetup()->assertRedirect(route('admin.index'));

    $admin = User::query()->where('email', 'ana@example.com')->sole();
    expect($admin->isAdmin())->toBeTrue()
        ->and($admin->hasVerifiedEmail())->toBeTrue()
        ->and($admin->timezone)->toBe('America/Sao_Paulo')
        ->and(Branding::current()->name)->toBe('Larkspur Support');
    $this->assertAuthenticatedAs($admin);

    // Once there is an administrator, the screen is gone and the usual pages are back.
    $this->get(route('setup.show'))->assertNotFound();
    completeSetup(['email' => 'mallory@example.com'])->assertNotFound();
    $this->get('/')->assertRedirect('/help');
    expect(User::query()->count())->toBe(1);
});

test('setup needs the code from the server', function () {
    completeSetup(['code' => 'guessed'])->assertSessionHasErrors('code');

    expect(User::query()->count())->toBe(0);
});

test('installations with staff never show the setup screen', function () {
    User::factory()->agent()->create();

    $this->get(route('login'))->assertOk();
    $this->get(route('setup.show'))->assertNotFound();
});

test('installations that create their administrators elsewhere can turn the screen off', function () {
    config(['kitedesk.setup_screen' => false]);

    $this->get(route('login'))->assertOk();
    $this->get(route('setup.show'))->assertNotFound();
    completeSetup()->assertNotFound();
});
