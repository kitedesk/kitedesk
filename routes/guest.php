<?php

use App\Http\Controllers\Portal\GuestAccessController;
use App\Http\Controllers\Portal\GuestTicketController;
use App\Http\Controllers\Portal\SatisfactionController;
use Illuminate\Support\Facades\Route;

/*
 * Requests followed without an account. Magic links always work (agents also open tickets for
 * people without accounts); the public form itself can be turned off with KITEDESK_GUEST_TICKETS.
 */
Route::prefix('requests')->name('guest.')->group(function () {
    Route::get('new', [GuestTicketController::class, 'create'])->name('tickets.create');
    Route::post('/', [GuestTicketController::class, 'store'])->middleware('throttle:10,1')->name('tickets.store');

    Route::get('check', [GuestAccessController::class, 'create'])->name('check');
    Route::post('check', [GuestAccessController::class, 'store'])->middleware('throttle:5,1')->name('check.store');

    // The star links in the satisfaction survey email.
    Route::get('satisfaction/{rating}', [SatisfactionController::class, 'show'])->middleware('signed')->name('satisfaction.show');
    Route::post('satisfaction/{rating}', [SatisfactionController::class, 'store'])->middleware(['signed', 'throttle:20,1'])->name('satisfaction.store');

    Route::get('{ticket}/access/{user}', [GuestAccessController::class, 'access'])->middleware('signed')->name('tickets.access');
    Route::get('{ticket}', [GuestTicketController::class, 'show'])->whereNumber('ticket')->name('tickets.show');
    Route::post('{ticket}/replies', [GuestTicketController::class, 'reply'])->middleware('throttle:20,1')->name('tickets.replies.store');
    Route::post('{ticket}/satisfaction', [SatisfactionController::class, 'guest'])->middleware('throttle:20,1')->name('tickets.satisfaction.store');
    Route::get('{ticket}/attachments/{media}', [GuestTicketController::class, 'attachment'])->name('tickets.attachments.show');
});
