<?php

use App\Domain\Support\Extensions\KiteDesk;
use App\Http\Controllers\Settings\ApiTokenController;
use App\Http\Controllers\Settings\BrowserSessionController;
use App\Http\Controllers\Settings\ConnectedAppController;
use App\Http\Controllers\Settings\ProfileAvatarController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\SecurityController;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', '/settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::post('settings/profile/avatar', [ProfileAvatarController::class, 'update'])->name('profile.avatar.update');
    Route::delete('settings/profile/avatar', [ProfileAvatarController::class, 'destroy'])->name('profile.avatar.destroy');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('settings/security', [SecurityController::class, 'edit'])
        ->middleware(RequirePassword::class)
        ->name('security.edit');

    Route::put('settings/password', [SecurityController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('user-password.update');

    Route::delete('settings/sessions', [BrowserSessionController::class, 'destroy'])
        ->middleware('throttle:6,1')
        ->name('browser-sessions.destroy');

    Route::inertia('settings/appearance', 'settings/appearance')->name('appearance.edit');

    Route::middleware('staff')->group(function () {
        Route::get('settings/connected-apps', [ConnectedAppController::class, 'index'])->name('connected-apps.index');
        Route::delete('settings/connected-apps/{client}', [ConnectedAppController::class, 'destroy'])->name('connected-apps.destroy');

        Route::middleware('entitlement:api')->group(function () {
            Route::get('settings/api-tokens', [ApiTokenController::class, 'index'])->name('api-tokens.index');
            Route::post('settings/api-tokens', [ApiTokenController::class, 'store'])->name('api-tokens.store');
            Route::delete('settings/api-tokens/{apiToken}', [ApiTokenController::class, 'destroy'])->name('api-tokens.destroy');
        });
    });

    KiteDesk::loadRoutes('settings');
});

Route::get('.well-known/passkey-endpoints', function () {
    return response()->json([
        'enroll' => route('security.edit'),
        'manage' => route('security.edit'),
    ]);
})->name('well-known.passkeys');
