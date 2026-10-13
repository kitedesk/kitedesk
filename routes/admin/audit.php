<?php

use App\Http\Controllers\Admin\AuditLogController;
use Illuminate\Support\Facades\Route;

Route::middleware(['permission:admin.audit_log', 'entitlement:audit_log'])->group(function () {
    Route::get('audit-log', [AuditLogController::class, 'index'])->name('audit-log.index');
    Route::get('audit-log/export', [AuditLogController::class, 'export'])->name('audit-log.export');
});
