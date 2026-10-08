<?php

use App\Domain\Accounts\Enums\UserType;
use App\Domain\Support\Installation;
use App\Models\User;
use Illuminate\Support\Facades\File;
use Laravel\Passport\Passport;

beforeEach(function () {
    $this->keyPath = storage_path('framework/testing/setup-keys-'.getmypid());
    File::ensureDirectoryExists($this->keyPath);
    Passport::loadKeysFrom($this->keyPath);
});

afterEach(function () {
    Passport::$keyPath = null;
    File::deleteDirectory($this->keyPath);
});

test('a new installation gets its OAuth keys and first administrator from the environment', function () {
    config(['kitedesk.first_admin' => ['name' => 'Ana', 'email' => 'Ana@Example.com', 'password' => 'a-Long-passw0rd!']]);

    $this->artisan('kitedesk:setup')->assertSuccessful();

    expect(Passport::keyPath('oauth-private.key'))->toBeFile()
        ->and(User::query()->where('email', 'ana@example.com')->sole()->isAdmin())->toBeTrue();

    // Every later start leaves the keys and the people alone.
    $privateKey = File::get(Passport::keyPath('oauth-private.key'));
    config(['kitedesk.first_admin.email' => 'other@example.com']);

    $this->artisan('kitedesk:setup')->assertSuccessful();

    expect(File::get(Passport::keyPath('oauth-private.key')))->toBe($privateKey)
        ->and(User::query()->where('type', UserType::Staff)->count())->toBe(1);
});

test('keys from the environment are used instead of generated ones', function () {
    config(['passport.private_key' => 'pem contents']);

    // Without KITEDESK_ADMIN_*, the setup screen creates the administrator.
    $this->artisan('kitedesk:setup')
        ->expectsOutputToContain(Installation::setupUrl())
        ->assertSuccessful();

    expect(Passport::keyPath('oauth-private.key'))->not->toBeFile()
        ->and(User::query()->count())->toBe(0);
});

test('setup stops without an app key or an administrator password', function () {
    config(['kitedesk.first_admin' => ['name' => 'Ana', 'email' => 'ana@example.com', 'password' => null]]);

    $this->artisan('kitedesk:setup')
        ->expectsOutputToContain('KITEDESK_ADMIN_PASSWORD')
        ->assertFailed();

    config(['app.key' => '']);

    $this->artisan('kitedesk:setup')
        ->expectsOutputToContain('APP_KEY is not set')
        ->assertFailed();
});
