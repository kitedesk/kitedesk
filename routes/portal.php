<?php

use App\Http\Controllers\Portal\SatisfactionController;
use App\Http\Controllers\Portal\TicketController;
use App\Http\Controllers\Portal\TicketReplyController;
use Illuminate\Support\Facades\Route;

Route::prefix('portal')
    ->name('portal.')
    ->middleware(['auth', 'verified'])
    ->group(function () {
        Route::redirect('/', '/portal/tickets')->name('home');

        Route::resource('tickets', TicketController::class)->only(['index', 'create', 'store', 'show']);
        Route::post('tickets/{ticket}/replies', [TicketReplyController::class, 'store'])->name('tickets.replies.store');
        Route::post('tickets/{ticket}/satisfaction', [SatisfactionController::class, 'portal'])->name('tickets.satisfaction.store');
    });
