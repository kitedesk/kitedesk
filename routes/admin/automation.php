<?php

use App\Http\Controllers\Admin\RoutingRuleController;
use App\Http\Controllers\Admin\WorkflowController;
use App\Http\Controllers\Admin\WorkflowTestController;
use Illuminate\Support\Facades\Route;

Route::middleware('permission:admin.automation')->group(function () {
    Route::patch('routing-rules/{routing_rule}/toggle', [RoutingRuleController::class, 'toggle'])->name('routing-rules.toggle');
    Route::post('routing-rules/{routing_rule}/move', [RoutingRuleController::class, 'move'])->name('routing-rules.move');
    Route::resource('routing-rules', RoutingRuleController::class)->except(['show']);
});

Route::middleware(['permission:admin.automation', 'entitlement:workflows'])->group(function () {
    Route::post('workflows/test', WorkflowTestController::class)->name('workflows.test');
    Route::patch('workflows/{workflow}/toggle', [WorkflowController::class, 'toggle'])->name('workflows.toggle');
    Route::post('workflows/{workflow}/duplicate', [WorkflowController::class, 'duplicate'])->name('workflows.duplicate');
    Route::post('workflows/{workflow}/runs/{run}/cancel', [WorkflowController::class, 'cancelRun'])->name('workflows.runs.cancel');
    Route::resource('workflows', WorkflowController::class)->except(['show']);
});
