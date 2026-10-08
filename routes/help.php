<?php

use App\Http\Controllers\Help\HelpCenterController;
use Illuminate\Support\Facades\Route;

Route::prefix('help')->name('help.')->group(function () {
    Route::get('/', [HelpCenterController::class, 'index'])->name('index');
    Route::get('search', [HelpCenterController::class, 'search'])->name('search');
    Route::get('categories/{category}', [HelpCenterController::class, 'category'])->name('categories.show');
    Route::get('articles/{article}', [HelpCenterController::class, 'article'])->name('articles.show');
    Route::post('articles/{article}/feedback', [HelpCenterController::class, 'feedback'])
        ->middleware('throttle:10,1')
        ->name('articles.feedback');
});
