<?php

use App\Domain\Support\Extensions\KiteDesk;
use Illuminate\Support\Facades\Route;

/*
 * Admin center. Each area registers its routes in routes/admin/{area}.php and is
 * loaded inside this group (prefix "admin", name "admin."). The group admits staff whose
 * role grants any admin section; each area guards its routes with that section's
 * permission (`permission:admin.*`). KiteDesk packages add theirs with `KiteDesk::adminRoutes()`.
 */
Route::prefix('admin')
    ->name('admin.')
    ->middleware(['auth', 'verified', 'admin'])
    ->group(function () {
        Route::inertia('/', 'admin/index')->name('index');

        foreach (glob(__DIR__.'/admin/*.php') ?: [] as $file) {
            require $file;
        }

        KiteDesk::loadRoutes('admin');
    });
