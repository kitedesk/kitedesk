<?php

use App\Domain\Support\Installation;
use App\Http\Controllers\AvatarController;
use App\Http\Controllers\BrandingAssetController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\SetupController;
use Illuminate\Support\Facades\Route;

// A new installation sends visitors to the setup screen until it has an administrator.
Route::get('/', fn () => redirect(Installation::needsSetup() ? route('setup.show') : '/help'))->name('home');

Route::controller(SetupController::class)->prefix('setup')->name('setup.')->group(function () {
    Route::get('/', 'show')->name('show');
    Route::post('/', 'store')->middleware('throttle:10,1')->name('store');
});

Route::get('branding/{file}', BrandingAssetController::class)
    ->where('file', '[A-Za-z0-9]+\.[a-z]{3,4}')
    ->name('branding.asset');

Route::get('avatars/{file}', AvatarController::class)
    ->where('file', '[A-Za-z0-9]+\.[a-z]{3,4}')
    ->name('avatars.show');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');
});

require __DIR__.'/settings.php';
require __DIR__.'/agent.php';
require __DIR__.'/admin.php';
require __DIR__.'/portal.php';
require __DIR__.'/guest.php';
require __DIR__.'/secrets.php';
require __DIR__.'/help.php';
require __DIR__.'/inbound.php';
require __DIR__.'/widget.php';
