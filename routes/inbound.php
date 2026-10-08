<?php

use App\Http\Controllers\InboundEmailController;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Route;

/*
 * Inbound email webhooks. Providers can't send a CSRF token; each request is authenticated
 * with the mailbox secret or the provider's signature instead.
 */
Route::prefix('inbound')
    ->name('inbound.')
    ->withoutMiddleware([ValidateCsrfToken::class])
    ->middleware('throttle:120,1')
    ->group(function () {
        Route::post('postmark/{mailbox}', [InboundEmailController::class, 'postmark'])->name('postmark');
        Route::post('mailgun/{mailbox}', [InboundEmailController::class, 'mailgun'])->name('mailgun');
    });
