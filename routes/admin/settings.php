<?php

use App\Http\Controllers\Admin\BrandingController;
use App\Http\Controllers\Admin\EmailPreviewController;
use App\Http\Controllers\Admin\SecurityPolicyController;
use App\Http\Controllers\Admin\SidebarController;
use Illuminate\Support\Facades\Route;

Route::middleware('permission:admin.branding')->group(function () {
    Route::get('branding', [BrandingController::class, 'edit'])->name('branding.edit');
    Route::post('branding', [BrandingController::class, 'update'])->name('branding.update');
    Route::get('sidebar', [SidebarController::class, 'edit'])->name('sidebar.edit');
    Route::put('sidebar', [SidebarController::class, 'update'])->name('sidebar.update');
});

// The email preview on the branding page and the email template editor.
Route::get('email-preview', EmailPreviewController::class)
    ->middleware(['permission:admin.branding|admin.email', 'throttle:60,1'])
    ->name('email-preview');

Route::middleware('permission:admin.security')->group(function () {
    Route::get('security', [SecurityPolicyController::class, 'edit'])->name('security.edit');
    Route::put('security', [SecurityPolicyController::class, 'update'])->name('security.update');
});
