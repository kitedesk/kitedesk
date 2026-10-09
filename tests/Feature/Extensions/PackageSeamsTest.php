<?php

use App\Domain\Accounts\Support\RoleCatalog;
use App\Domain\Support\Extensions\KiteDesk;
use App\Models\User;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission as PermissionModel;
use Spatie\Permission\PermissionRegistrar;
use Tests\Fixtures\KiteDeskDemo\DemoServiceProvider;

beforeEach(function () {
    $this->app->register(DemoServiceProvider::class);

    // Routes were loaded before the package registered; load the admin area again.
    Route::middleware('web')->group(base_path('routes/admin.php'));
    Route::getRoutes()->refreshNameLookups();

    RoleCatalog::sync();
});

test('package admin pages load inside the admin center and render from the package', function () {
    $route = Route::getRoutes()->getByName('admin.demo');

    expect($route->uri())->toBe('admin/demo')
        ->and($route->gatherMiddleware())->toContain('auth', 'verified', 'admin', 'permission:admin.demo_billing')
        ->and(KiteDesk::pageEntry('demo::admin/demo'))->toBe('tests/Fixtures/KiteDeskDemo/resources/js/pages/admin/demo.tsx')
        ->and(KiteDesk::pageEntry('admin/index'))->toBe('resources/js/pages/admin/index.tsx');

    $this->actingAs(User::factory()->admin()->create())
        ->get('/admin/demo')
        ->assertInertia(fn (Assert $page) => $page->component('demo::admin/demo')->where('auth.can', fn ($can) => $can['admin.demo_billing'] === true));

    $this->actingAs(User::factory()->create())->get('/admin/demo')->assertForbidden();
});

test('package permissions survive the role sync and reach agents and the role form', function () {
    $admin = User::factory()->admin()->create();

    expect(PermissionModel::query()->where('name', 'admin.demo_billing')->exists())->toBeTrue()
        ->and(RoleCatalog::administrator()->hasPermissionTo('admin.demo_billing'))->toBeTrue();

    $agent = User::factory()->agent()->create();
    expect($agent->hasPermission('admin.demo_billing'))->toBeTrue()
        ->and($agent->canAccessAdmin())->toBeTrue();

    $this->actingAs($admin)->get(route('admin.roles.create'))
        ->assertInertia(fn (Assert $page) => $page->where(
            'permissionGroups',
            fn ($groups) => collect($groups)->flatMap(fn ($group) => $group['permissions'])->contains('value', 'admin.demo_billing'),
        ));
});

test('a permission added by a package later is given to the existing agent role', function () {
    $agent = User::factory()->agent()->create();
    PermissionModel::query()->where('name', 'admin.demo_billing')->delete();
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    RoleCatalog::sync();

    expect($agent->fresh()->hasPermission('admin.demo_billing'))->toBeTrue();
});

test('package translations are sent with the installation language', function () {
    config(['kitedesk.default_locale' => 'pt_BR']);

    $this->get(route('help.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('translations.Demo billing', 'Cobrança de demonstração')
            ->where('translations.Submit a request', 'Enviar uma solicitação'));

    expect(__('Demo billing'))->toBe('Cobrança de demonstração');
});
