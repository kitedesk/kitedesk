<?php

use App\Http\Controllers\Admin\GroupController;
use App\Http\Controllers\Admin\OrganizationController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware('permission:admin.team')->group(function () {
    Route::resource('users', UserController::class);
    Route::post('users/{user}/invitation', [UserController::class, 'resendInvitation'])->name('users.invitation');
    Route::post('users/{user}/deactivate', [UserController::class, 'deactivate'])->name('users.deactivate');
    Route::post('users/{user}/reactivate', [UserController::class, 'reactivate'])->name('users.reactivate');
    Route::delete('users/{user}/sessions', [UserController::class, 'destroySessions'])->name('users.sessions.destroy');
    Route::delete('users/{user}/two-factor', [UserController::class, 'resetTwoFactor'])->name('users.two-factor.destroy');

    Route::resource('roles', RoleController::class)->only(['index', 'edit', 'update', 'destroy']);
    Route::resource('roles', RoleController::class)->only(['create', 'store'])->middleware('entitlement:custom_roles');

    Route::resource('groups', GroupController::class)->except(['show', 'create', 'edit']);
    Route::resource('organizations', OrganizationController::class)->except(['show', 'create', 'edit']);
});
