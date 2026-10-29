<?php

use App\Domain\Accounts\Enums\UserType;
use App\Domain\Accounts\Models\Role;
use App\Domain\Accounts\Support\RoleCatalog;
use App\Domain\Audit\AuditLog;
use App\Domain\Support\Extensions\ModuleAvailability;
use App\Domain\Support\Extensions\ModuleDefinition;
use App\Domain\Support\Extensions\ModuleRegistry;
use App\Domain\Support\Models\ModuleState;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\Fixtures\KiteDeskDemo\ModuleDemoServiceProvider;

beforeEach(function () {
    $this->app->register(ModuleDemoServiceProvider::class);

    // Routes were loaded before the module registered; load the admin, agent and portal areas again.
    Route::middleware('web')->group(base_path('routes/admin.php'));
    Route::middleware('web')->group(base_path('routes/agent.php'));
    Route::middleware('web')->group(base_path('routes/portal.php'));
    Route::getRoutes()->refreshNameLookups();

    RoleCatalog::sync();

    $this->admin = User::factory()->admin()->create();
});

/**
 * Installs and switches a module on, the two steps an admin takes.
 */
function installAndEnable(string $id, ?User $actor = null): void
{
    $registry = app(ModuleRegistry::class);
    $registry->install($id, $actor);
    $registry->enable($id, $actor);
}

function permissionValues(Assert $page): array
{
    return collect($page->toArray()['props']['permissionGroups'])
        ->flatMap(fn (array $group) => $group['permissions'])
        ->pluck('value')
        ->all();
}

test('a registered module is disabled until an admin enables it', function () {
    expect(app(ModuleRegistry::class)->isEnabled('demo-module'))->toBeFalse();

    $this->actingAs($this->admin)->get('/admin/demo-module')->assertNotFound();

    $this->actingAs($this->admin)->get(route('admin.modules.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/modules/index')
            ->where('modules', function ($modules): bool {
                $modules = collect($modules)->keyBy('id');

                return $modules['demo-module']['enabled'] === false
                    && $modules['demo-module']['installed'] === false
                    && $modules['demo-addon']['requires'][0]['name'] === 'Demo module';
            }));
});

test('enabling a module switches its routes, permissions and shared prop on', function () {
    $this->actingAs($this->admin)->put(route('admin.modules.update', 'demo-module'), ['action' => 'install'])->assertRedirect();
    $this->put(route('admin.modules.update', 'demo-module'), ['action' => 'enable'])->assertRedirect();

    $this->get('/admin/demo-module')->assertOk()->assertSee('demo module');

    $this->get(route('admin.roles.create'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('modules', ['demo-module'])
            ->etc());

    expect(ModuleDemoServiceProvider::$enabledCalls)->toBe(1)
        ->and(app(ModuleRegistry::class)->isEnabled('demo-module'))->toBeTrue();

    $state = ModuleState::query()->find('demo-module');
    expect($state->enabled)->toBeTrue()
        ->and($state->installed_version)->toBe('1.2.0')
        ->and($state->enabled_by)->toBe($this->admin->id);
});

test('disabling a module hides it again but keeps role grants for when it returns', function () {
    $registry = app(ModuleRegistry::class);
    installAndEnable('demo-module', $this->admin);

    $agentRole = Role::query()->where('name', RoleCatalog::AGENT)->firstOrFail();
    expect($agentRole->hasPermissionTo('admin.demo_module'))->toBeTrue();

    $this->actingAs($this->admin)->get(route('admin.roles.create'))
        ->assertInertia(fn (Assert $page) => expect(permissionValues($page))->toContain('admin.demo_module'));

    $this->put(route('admin.modules.update', 'demo-module'), ['action' => 'disable'])->assertRedirect();

    expect(ModuleDemoServiceProvider::$disabledCalls)->toBe(1);

    $this->get('/admin/demo-module')->assertNotFound();
    $this->get(route('admin.roles.create'))
        ->assertInertia(fn (Assert $page) => expect(permissionValues($page))->not->toContain('admin.demo_module'));

    // Saving a role while the module is off must not drop what the module granted.
    $this->put(route('admin.roles.update', $agentRole), [
        'name' => $agentRole->name,
        'description' => $agentRole->description,
        'ticket_access' => $agentRole->ticket_access->value,
        'permissions' => ['tickets.create'],
    ]);

    expect($agentRole->fresh()->hasPermissionTo('admin.demo_module'))->toBeTrue();

    // Their only admin section comes from the module, so the admin center is closed while it is off.
    $agent = User::factory()->agent()->create();
    expect($agent->canAccessAdmin())->toBeFalse();

    $registry->enable('demo-module', $this->admin);

    expect($agent->fresh()->canAccessAdmin())->toBeTrue();
});

test('modules respect their requirements in both directions', function () {
    $registry = app(ModuleRegistry::class);

    $this->actingAs($this->admin)
        ->from(route('admin.modules.index'))
        ->put(route('admin.modules.update', 'demo-addon'), ['action' => 'install'])
        ->assertSessionHasErrors(['module' => 'Install Demo module first.']);

    $registry->install('demo-module');
    $registry->install('demo-addon');

    $this->put(route('admin.modules.update', 'demo-addon'), ['action' => 'enable'])
        ->assertSessionHasErrors(['module' => 'Enable Demo module first.']);

    expect($registry->isEnabled('demo-addon'))->toBeFalse();

    $registry->enable('demo-module');
    $registry->enable('demo-addon');

    $this->put(route('admin.modules.update', 'demo-module'), ['action' => 'disable'])
        ->assertSessionHasErrors(['module' => 'Disable Demo add-on first.']);

    expect($registry->isEnabled('demo-module'))->toBeTrue();
});

test('a module the installation may not use cannot be enabled', function () {
    $this->app->instance(ModuleAvailability::class, new class implements ModuleAvailability
    {
        public function isAvailable(ModuleDefinition $module): bool
        {
            return false;
        }
    });
    $this->app->forgetInstance(ModuleRegistry::class);
    $this->app->singleton(ModuleRegistry::class);
    $this->app->register(ModuleDemoServiceProvider::class, force: true);

    $this->actingAs($this->admin)
        ->from(route('admin.modules.index'))
        ->put(route('admin.modules.update', 'demo-module'), ['action' => 'install'])
        ->assertSessionHasErrors(['module' => 'Your plan does not include this module.']);

    expect(app(ModuleRegistry::class)->isEnabled('demo-module'))->toBeFalse();
});

test('only people with the modules permission can manage modules', function () {
    $this->actingAs(User::factory()->agent()->create())
        ->put(route('admin.modules.update', 'demo-module'), ['action' => 'install'])
        ->assertForbidden();

    $this->get(route('admin.modules.index'))->assertForbidden();

    expect(app(ModuleRegistry::class)->isEnabled('demo-module'))->toBeFalse();
});

test('switching a module is recorded in the audit log', function () {
    $this->actingAs($this->admin);

    foreach (['install', 'enable', 'disable', 'uninstall'] as $action) {
        $this->put(route('admin.modules.update', 'demo-module'), ['action' => $action, 'confirm' => true]);
    }

    $events = Activity::query()->whereIn('event', ['module_installed', 'module_enabled', 'module_disabled', 'module_uninstalled'])->orderBy('id')->get();

    expect($events->pluck('event')->all())->toBe(['module_installed', 'module_enabled', 'module_disabled', 'module_uninstalled'])
        ->and($events->first()->causer_id)->toBe($this->admin->id)
        ->and($events->first()->properties['module'])->toBe('demo-module');
});

test('modules can be managed from the command line', function () {
    $this->artisan('modules:list')->expectsOutputToContain('demo-module')->assertSuccessful();
    $this->artisan('modules:enable', ['module' => 'demo-module'])->expectsOutputToContain('Install Demo module first.')->assertFailed();
    $this->artisan('modules:install', ['module' => 'demo-module'])->assertSuccessful();
    $this->artisan('modules:enable', ['module' => 'demo-module'])->assertSuccessful();

    expect(app(ModuleRegistry::class)->isEnabled('demo-module'))->toBeTrue();

    $this->artisan('modules:uninstall', ['module' => 'demo-module', '--force' => true])->expectsOutputToContain('Disable Demo module before uninstalling it.')->assertFailed();
    $this->artisan('modules:disable', ['module' => 'demo-module'])->assertSuccessful();

    expect(app(ModuleRegistry::class)->isEnabled('demo-module'))->toBeFalse();

    $this->artisan('modules:uninstall', ['module' => 'demo-module'])->expectsConfirmation('Uninstalling demo-module deletes all of its data. Continue?', 'no')->assertFailed();

    expect(app(ModuleRegistry::class)->isInstalled('demo-module'))->toBeTrue();

    $this->artisan('modules:uninstall', ['module' => 'demo-module', '--force' => true])->assertSuccessful();

    expect(app(ModuleRegistry::class)->isInstalled('demo-module'))->toBeFalse();
});

test('a module adds routes to the agent workspace, live only while it is enabled', function () {
    $agent = User::factory()->agent()->create();

    $this->actingAs($agent)->get('/agent/demo-module')->assertNotFound();

    installAndEnable('demo-module');

    $this->get('/agent/demo-module')->assertOk()->assertSee('demo agent page');

    $route = Route::getRoutes()->getByName('agent.demo-module');
    expect($route->gatherMiddleware())->toContain('auth', 'verified', 'staff', 'module:demo-module');

    $this->actingAs(User::factory()->create())->get('/agent/demo-module')->assertForbidden();
});

test('a module can add pages to the customer portal, which answer 404 while it is disabled', function () {
    $customer = User::factory()->create();

    $this->actingAs($customer)->get('/portal/demo-module')->assertNotFound();

    installAndEnable('demo-module');

    $this->actingAs($customer)->get('/portal/demo-module')->assertOk()->assertSee('demo portal page');

    expect(Route::getRoutes()->getByName('portal.demo-module')->gatherMiddleware())->toContain('auth', 'verified', 'module:demo-module');
});

test('a module adds a category to the audit log', function () {
    expect(AuditLog::categories())->toHaveKey('demo-module', 'Demo module');

    activity()->useLog('demo-module')->causedBy($this->admin)->event('created')->log('created');

    $this->actingAs($this->admin)->get(route('admin.audit-log.index', ['category' => 'demo-module']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.category', 'demo-module')
            ->has('entries.data', 1));
});

test('a permission that is not stored yet counts as not granted instead of failing', function () {
    $staff = User::factory()->create(['type' => UserType::Staff]);
    Permission::query()->where('name', 'admin.modules')->delete();
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    expect($staff->hasPermission('admin.modules'))->toBeFalse()
        ->and($staff->canAccessAdmin())->toBeFalse();
});

test('installing runs the module\'s migrations; uninstalling rolls them back and deletes its data', function () {
    $registry = app(ModuleRegistry::class);

    expect(Schema::hasTable('demo_module_items'))->toBeFalse();

    $this->actingAs($this->admin)->put(route('admin.modules.update', 'demo-module'), ['action' => 'install'])->assertSessionHasNoErrors();

    $state = ModuleState::query()->findOrFail('demo-module');
    expect(Schema::hasTable('demo_module_items'))->toBeTrue()
        ->and($registry->isInstalled('demo-module'))->toBeTrue()
        ->and($registry->isEnabled('demo-module'))->toBeFalse()
        ->and($state->installed_version)->toBe('1.2.0')
        ->and(ModuleDemoServiceProvider::$installedCalls)->toBe(1);

    $this->get('/admin/demo-module')->assertNotFound();

    $this->put(route('admin.modules.update', 'demo-module'), ['action' => 'install']);
    expect(ModuleDemoServiceProvider::$installedCalls)->toBe(1);

    DB::table('demo_module_items')->insert(['name' => 'kept while disabled']);
    $registry->enable('demo-module');
    $registry->disable('demo-module');
    expect(DB::table('demo_module_items')->count())->toBe(1);

    $this->put(route('admin.modules.update', 'demo-module'), ['action' => 'uninstall'])->assertSessionHasErrors('confirm');
    expect(Schema::hasTable('demo_module_items'))->toBeTrue();

    $this->put(route('admin.modules.update', 'demo-module'), ['action' => 'uninstall', 'confirm' => true])->assertSessionHasNoErrors();

    expect(Schema::hasTable('demo_module_items'))->toBeFalse()
        ->and($registry->isInstalled('demo-module'))->toBeFalse()
        ->and(ModuleState::query()->findOrFail('demo-module'))->installed_version->toBeNull()
        ->and(ModuleDemoServiceProvider::$uninstalledCalls)->toBe(1);

    $this->put(route('admin.modules.update', 'demo-module'), ['action' => 'install']);
    expect(Schema::hasTable('demo_module_items'))->toBeTrue()->and(DB::table('demo_module_items')->count())->toBe(0);
});

test('a module that was installed before an upgrade is brought up to date', function () {
    $registry = app(ModuleRegistry::class);
    $registry->install('demo-module');
    ModuleState::query()->whereKey('demo-module')->update(['installed_version' => '1.0.0']);
    $registry->flush();
    Cache::forget('modules:state');

    expect($registry->needsUpdate('demo-module'))->toBeTrue();

    $this->artisan('modules:list')->expectsOutputToContain('needs update')->assertSuccessful();
    $this->artisan('modules:migrate')->expectsOutputToContain('Updated demo-module.')->assertSuccessful();

    expect($registry->needsUpdate('demo-module'))->toBeFalse()
        ->and($registry->installedVersion('demo-module'))->toBe('1.2.0');

    $this->artisan('modules:migrate')->expectsOutputToContain('up to date')->assertSuccessful();

    ModuleState::query()->whereKey('demo-module')->update(['installed_version' => '1.0.0']);
    Cache::forget('modules:state');
    $registry->flush();
    $this->actingAs($this->admin)->put(route('admin.modules.update', 'demo-module'), ['action' => 'update'])->assertSessionHasNoErrors();

    expect($registry->installedVersion('demo-module'))->toBe('1.2.0');
});

test('a module whose migrations fail stays uninstalled', function () {
    $registry = app(ModuleRegistry::class);
    Schema::create('demo_module_items', fn ($table) => $table->id());

    $this->actingAs($this->admin)->from(route('admin.modules.index'))
        ->put(route('admin.modules.update', 'demo-module'), ['action' => 'install'])
        ->assertSessionHasErrors('module');

    expect($registry->isInstalled('demo-module'))->toBeFalse();
});
