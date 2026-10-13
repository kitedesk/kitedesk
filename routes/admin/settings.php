<?php

use App\Http\Controllers\Admin\BrandingController;
use App\Http\Controllers\Admin\SecurityPolicyController;
use Illuminate\Support\Facades\Route;

Route::middleware('permission:admin.branding')->group(function () {
    Route::get('branding', [BrandingController::class, 'edit'])->name('branding.edit');
    Route::post('branding', [BrandingController::class, 'update'])->name('branding.update');
});

Route::middleware('permission:admin.security')->group(function () {
    Route::get('security', [SecurityPolicyController::class, 'edit'])->name('security.edit');
    Route::put('security', [SecurityPolicyController::class, 'update'])->name('security.update');
});
