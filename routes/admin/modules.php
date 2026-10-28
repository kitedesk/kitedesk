<?php

use App\Http\Controllers\Admin\ModuleController;
use Illuminate\Support\Facades\Route;

Route::middleware('permission:admin.modules')->group(function () {
    Route::get('modules', [ModuleController::class, 'index'])->name('modules.index');
    Route::put('modules/{module}', [ModuleController::class, 'update'])->name('modules.update');
});
