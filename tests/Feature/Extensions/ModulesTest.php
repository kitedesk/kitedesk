<?php

use App\Domain\Accounts\Models\Role;
use App\Domain\Accounts\Support\RoleCatalog;
use App\Domain\Support\Extensions\ModuleAvailability;
use App\Domain\Support\Extensions\ModuleDefinition;
use App\Domain\Support\Extensions\ModuleRegistry;
use App\Domain\Support\Models\ModuleState;
use App\Models\User;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Activitylog\Models\Activity;
use Tests\Fixtures\KiteDeskDemo\ModuleDemoServiceProvider;

beforeEach(function () {
    $this->app->register(ModuleDemoServiceProvider::class);

    // Routes were loaded before the module registered; load the admin area again.
    Route::middleware('web')->group(base_path('routes/admin.php'));
    Route::getRoutes()->refreshNameLookups();

    RoleCatalog::sync();

    $this->admin = User::factory()->admin()->create();
});

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
            ->where('modules.0.id', 'demo-module')
            ->where('modules.0.enabled', false)
            ->where('modules.1.requires.0.name', 'Demo module'));
});

test('enabling a module switches its routes, permissions and shared prop on', function () {
    $this->actingAs($this->admin)
        ->put(route('admin.modules.update', 'demo-module'), ['enabled' => true])
        ->assertRedirect();

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
    $registry->enable('demo-module', $this->admin);

    $agentRole = Role::query()->where('name', RoleCatalog::AGENT)->firstOrFail();
    expect($agentRole->hasPermissionTo('admin.demo_module'))->toBeTrue();

    $this->actingAs($this->admin)->get(route('admin.roles.create'))
        ->assertInertia(fn (Assert $page) => expect(permissionValues($page))->toContain('admin.demo_module'));

    $this->put(route('admin.modules.update', 'demo-module'), ['enabled' => false])->assertRedirect();

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
        ->put(route('admin.modules.update', 'demo-addon'), ['enabled' => true])
        ->assertSessionHasErrors(['module' => 'Enable Demo module first.']);

    expect($registry->isEnabled('demo-addon'))->toBeFalse();

    $registry->enable('demo-module');
    $registry->enable('demo-addon');

    $this->put(route('admin.modules.update', 'demo-module'), ['enabled' => false])
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
        ->put(route('admin.modules.update', 'demo-module'), ['enabled' => true])
        ->assertSessionHasErrors(['module' => 'Your plan does not include this module.']);

    expect(app(ModuleRegistry::class)->isEnabled('demo-module'))->toBeFalse();
});

test('only people with the modules permission can manage modules', function () {
    $this->actingAs(User::factory()->agent()->create())
        ->put(route('admin.modules.update', 'demo-module'), ['enabled' => true])
        ->assertForbidden();

    $this->get(route('admin.modules.index'))->assertForbidden();

    expect(app(ModuleRegistry::class)->isEnabled('demo-module'))->toBeFalse();
});

test('switching a module is recorded in the audit log', function () {
    $this->actingAs($this->admin)->put(route('admin.modules.update', 'demo-module'), ['enabled' => true]);
    $this->put(route('admin.modules.update', 'demo-module'), ['enabled' => false]);

    $events = Activity::query()->whereIn('event', ['module_enabled', 'module_disabled'])->orderBy('id')->get();

    expect($events->pluck('event')->all())->toBe(['module_enabled', 'module_disabled'])
        ->and($events->first()->causer_id)->toBe($this->admin->id)
        ->and($events->first()->properties['module'])->toBe('demo-module');
});

test('modules can be managed from the command line', function () {
    $this->artisan('modules:list')->expectsOutputToContain('demo-module')->assertSuccessful();
    $this->artisan('modules:enable', ['module' => 'demo-addon'])->expectsOutputToContain('Enable Demo module first.')->assertFailed();
    $this->artisan('modules:enable', ['module' => 'demo-module'])->assertSuccessful();

    expect(app(ModuleRegistry::class)->isEnabled('demo-module'))->toBeTrue();

    $this->artisan('modules:disable', ['module' => 'demo-module'])->assertSuccessful();

    expect(app(ModuleRegistry::class)->isEnabled('demo-module'))->toBeFalse();
});
