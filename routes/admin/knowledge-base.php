<?php

use App\Http\Controllers\Admin\KnowledgeBase\ArticleController;
use App\Http\Controllers\Admin\KnowledgeBase\CategoryController;
use App\Http\Controllers\Admin\KnowledgeBase\KnowledgeBaseController;
use App\Http\Controllers\Admin\KnowledgeBase\SectionController;
use Illuminate\Support\Facades\Route;

/*
 * Loaded inside the admin group (prefix "admin", name "admin.").
 * Models are bound by id here; the public help center binds them by slug.
 */
Route::prefix('knowledge-base')->name('knowledge-base.')->middleware('permission:admin.help_center')->group(function () {
    Route::get('/', [KnowledgeBaseController::class, 'index'])->name('index');

    Route::post('categories', [CategoryController::class, 'store'])->name('categories.store');
    Route::patch('categories/{category:id}', [CategoryController::class, 'update'])->name('categories.update');
    Route::delete('categories/{category:id}', [CategoryController::class, 'destroy'])->name('categories.destroy');
    Route::post('categories/{category:id}/move', [CategoryController::class, 'move'])->name('categories.move');

    Route::post('sections', [SectionController::class, 'store'])->name('sections.store');
    Route::patch('sections/{section:id}', [SectionController::class, 'update'])->name('sections.update');
    Route::delete('sections/{section:id}', [SectionController::class, 'destroy'])->name('sections.destroy');
    Route::post('sections/{section:id}/move', [SectionController::class, 'move'])->name('sections.move');

    Route::get('articles/create', [ArticleController::class, 'create'])->name('articles.create');
    Route::post('articles', [ArticleController::class, 'store'])->name('articles.store');
    Route::get('articles/{article:id}/edit', [ArticleController::class, 'edit'])->name('articles.edit');
    Route::patch('articles/{article:id}', [ArticleController::class, 'update'])->name('articles.update');
    Route::delete('articles/{article:id}', [ArticleController::class, 'destroy'])->name('articles.destroy');
});
