<?php

use App\Http\Controllers\Portal\SecretController;
use Illuminate\Support\Facades\Route;

/*
 * Secret links sent to customers. Only the signed-in requester and people copied on the
 * ticket can open them; responses are never cached.
 */
Route::prefix('s')->name('secrets.')->middleware(['auth', 'verified', 'cache.headers:no_store;private'])->group(function () {
    Route::get('{secret}', [SecretController::class, 'show'])->name('show');
    Route::post('{secret}', [SecretController::class, 'submit'])->middleware('throttle:10,1')->name('submit');
    Route::post('{secret}/reveal', [SecretController::class, 'reveal'])->middleware('throttle:10,1')->name('reveal');
});
