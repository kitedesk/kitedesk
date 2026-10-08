<?php

namespace Tests\Fixtures\KiteDeskDemo;

use App\Domain\Accounts\Support\PermissionDefinition;
use App\Domain\Support\Extensions\KiteDesk;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * A stand-in for a first-party KiteDesk package, hooking in the way a real one does.
 */
class DemoServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        KiteDesk::permissions(new PermissionDefinition(
            name: 'admin.demo_billing',
            label: 'Demo billing',
            group: 'Admin center',
            grantToAgents: true,
        ));

        KiteDesk::pages('demo', __DIR__.'/resources/js/pages');
        KiteDesk::translations(__DIR__.'/lang');

        KiteDesk::adminRoutes(function (): void {
            Route::inertia('demo', 'demo::admin/demo')->middleware('permission:admin.demo_billing')->name('demo');
        });
    }
}
