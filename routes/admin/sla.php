<?php

use App\Http\Controllers\Admin\Sla\BusinessScheduleController;
use App\Http\Controllers\Admin\Sla\HolidayController;
use App\Http\Controllers\Admin\Sla\SlaPolicyController;
use Illuminate\Support\Facades\Route;

Route::middleware(['permission:admin.sla', 'entitlement:sla'])->group(function () {
    Route::patch('sla-policies/{policy}/toggle', [SlaPolicyController::class, 'toggle'])->name('sla-policies.toggle');
    Route::patch('sla-policies/{policy}/move', [SlaPolicyController::class, 'move'])->name('sla-policies.move');
    Route::resource('sla-policies', SlaPolicyController::class)
        ->parameters(['sla-policies' => 'policy'])
        ->except(['show']);

    Route::resource('business-schedules', BusinessScheduleController::class)
        ->parameters(['business-schedules' => 'schedule'])
        ->except(['show']);
    Route::post('business-schedules/{schedule}/holidays', [HolidayController::class, 'store'])->name('business-schedules.holidays.store');
    Route::delete('business-schedules/{schedule}/holidays/{holiday}', [HolidayController::class, 'destroy'])
        ->scopeBindings()
        ->name('business-schedules.holidays.destroy');
});
