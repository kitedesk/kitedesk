<?php

use App\Http\Controllers\WidgetController;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Route;

/*
 * The support widget other websites embed. Its frame runs in a third-party iframe, where
 * browsers don't send our cookies, so the form is protected by the CAPTCHA and a rate limit
 * instead of a CSRF token.
 */
Route::get('widget.js', [WidgetController::class, 'script'])->name('widget.script');

Route::prefix('widget')->name('widget.')->group(function () {
    Route::get('frame', [WidgetController::class, 'frame'])->name('frame');
    Route::post('tickets', [WidgetController::class, 'store'])
        ->withoutMiddleware([ValidateCsrfToken::class])
        ->middleware('throttle:10,1')
        ->name('tickets.store');
});
