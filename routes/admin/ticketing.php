<?php

use App\Http\Controllers\Admin\CustomStatusController;
use App\Http\Controllers\Admin\SatisfactionSurveyController;
use App\Http\Controllers\Admin\TicketCategoryController;
use App\Http\Controllers\Admin\TicketFieldController;
use App\Http\Controllers\Admin\TicketFormController;
use App\Http\Controllers\Admin\TicketNumberController;
use Illuminate\Support\Facades\Route;

Route::middleware('permission:admin.ticket_setup')->group(function () {
    Route::resource('ticket-forms', TicketFormController::class)->except(['show']);

    Route::resource('ticket-fields', TicketFieldController::class)->except(['show', 'create', 'edit']);
    Route::post('ticket-fields/{ticket_field}/move', [TicketFieldController::class, 'move'])->name('ticket-fields.move');

    Route::resource('ticket-categories', TicketCategoryController::class)->except(['show', 'create', 'edit']);
    Route::post('ticket-categories/{ticket_category}/move', [TicketCategoryController::class, 'move'])->name('ticket-categories.move');

    Route::resource('ticket-statuses', CustomStatusController::class)->except(['show', 'create', 'edit']);
    Route::post('ticket-statuses/{ticket_status}/move', [CustomStatusController::class, 'move'])->name('ticket-statuses.move');
    Route::post('ticket-statuses/{ticket_status}/default', [CustomStatusController::class, 'makeDefault'])->name('ticket-statuses.make-default');

    Route::get('ticket-numbers', [TicketNumberController::class, 'edit'])->name('ticket-numbers.edit');
    Route::put('ticket-numbers', [TicketNumberController::class, 'update'])->name('ticket-numbers.update');

    Route::middleware('entitlement:satisfaction')->group(function () {
        Route::get('satisfaction', [SatisfactionSurveyController::class, 'edit'])->name('satisfaction.edit');
        Route::put('satisfaction', [SatisfactionSurveyController::class, 'update'])->name('satisfaction.update');
    });
});
