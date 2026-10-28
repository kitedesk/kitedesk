<?php

namespace Tests\Fixtures\KiteDeskDemo;

use App\Domain\Accounts\Support\PermissionDefinition;
use App\Domain\Support\Extensions\KiteDesk;
use App\Domain\Support\Extensions\ModuleDefinition;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * A stand-in for a first-party module: `demo-module`, and `demo-addon`, which needs it.
 */
class ModuleDemoServiceProvider extends ServiceProvider
{
    public static int $enabledCalls = 0;

    public static int $disabledCalls = 0;

    public function boot(): void
    {
        self::$enabledCalls = 0;
        self::$disabledCalls = 0;

        KiteDesk::module(new ModuleDefinition(
            id: 'demo-module',
            name: 'Demo module',
            description: 'A module used by the tests.',
            version: '1.2.0',
            permissions: [
                new PermissionDefinition(
                    name: 'admin.demo_module',
                    label: 'Demo module',
                    group: 'Admin center',
                    grantToAgents: true,
                ),
            ],
            onEnable: function (): void {
                self::$enabledCalls++;
            },
            onDisable: function (): void {
                self::$disabledCalls++;
            },
        ));

        KiteDesk::module(new ModuleDefinition(
            id: 'demo-addon',
            name: 'Demo add-on',
            description: 'Needs the demo module.',
            version: '0.1.0',
            requires: ['demo-module'],
        ));

        KiteDesk::adminRoutes(function (): void {
            Route::get('demo-module', fn () => response('demo module'))->middleware('permission:admin.demo_module')->name('demo-module');
        }, 'demo-module');
    }
}
