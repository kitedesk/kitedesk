<?php

use App\Http\Controllers\Admin\BrandingController;
use Illuminate\Support\Facades\Route;

Route::middleware('permission:admin.branding')->group(function () {
    Route::get('branding', [BrandingController::class, 'edit'])->name('branding.edit');
    Route::post('branding', [BrandingController::class, 'update'])->name('branding.update');
});
