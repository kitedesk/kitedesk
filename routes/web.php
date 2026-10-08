<?php

use App\Http\Controllers\AvatarController;
use App\Http\Controllers\BrandingAssetController;
use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/help')->name('home');

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
