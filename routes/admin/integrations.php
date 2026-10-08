<?php

use App\Http\Controllers\Admin\AiSettingsController;
use App\Http\Controllers\Admin\ApiTokenController;
use App\Http\Controllers\Admin\WebhookController;
use App\Http\Controllers\Admin\WidgetSettingsController;
use Illuminate\Support\Facades\Route;

Route::middleware('permission:admin.integrations')->group(function () {
    Route::middleware('entitlement:webhooks')->group(function () {
        Route::resource('webhooks', WebhookController::class)->except(['create', 'edit']);
        Route::post('webhooks/{webhook}/test', [WebhookController::class, 'test'])->name('webhooks.test');
        Route::post('webhooks/{webhook}/secret', [WebhookController::class, 'rotateSecret'])->name('webhooks.secret');
        Route::post('webhooks/{webhook}/deliveries/{delivery}/redeliver', [WebhookController::class, 'redeliver'])->name('webhooks.deliveries.redeliver');
    });

    Route::middleware('entitlement:widget')->group(function () {
        Route::get('widget', [WidgetSettingsController::class, 'edit'])->name('widget.edit');
        Route::put('widget', [WidgetSettingsController::class, 'update'])->name('widget.update');
    });

    Route::middleware('entitlement:ai')->group(function () {
        Route::get('ai', [AiSettingsController::class, 'edit'])->name('ai.edit');
        Route::put('ai', [AiSettingsController::class, 'update'])->name('ai.update');
        Route::post('ai/test', [AiSettingsController::class, 'test'])->middleware('throttle:10,1')->name('ai.test');
    });

    Route::middleware('entitlement:api')->group(function () {
        Route::get('api-tokens', [ApiTokenController::class, 'index'])->name('api-tokens.index');
        Route::post('api-tokens', [ApiTokenController::class, 'store'])->name('api-tokens.store');
        Route::delete('api-tokens/{apiToken}', [ApiTokenController::class, 'destroy'])->name('api-tokens.destroy');
    });
});
