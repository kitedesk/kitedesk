<?php

use App\Http\Controllers\Admin\JiraSettingsController;
use Illuminate\Support\Facades\Route;

/*
 * The Jira module's admin page. Loaded inside the admin group, and only answering while the
 * module is enabled (see JiraServiceProvider).
 */
Route::middleware('permission:admin.integrations')->group(function () {
    Route::get('jira', [JiraSettingsController::class, 'edit'])->name('jira.edit');
    Route::put('jira', [JiraSettingsController::class, 'update'])->name('jira.update');
    Route::post('jira/test', [JiraSettingsController::class, 'test'])->middleware('throttle:10,1')->name('jira.test');
    Route::post('jira/secret', [JiraSettingsController::class, 'rotateSecret'])->name('jira.secret');
    Route::get('jira/issue-types', [JiraSettingsController::class, 'issueTypes'])->middleware('throttle:30,1')->name('jira.issue-types');
});
