<?php

use App\Http\Controllers\Agent\JiraIssueController;
use Illuminate\Support\Facades\Route;

/*
 * The Jira panel on the ticket page. Loaded inside the agent group, and only answering while
 * the module is enabled (see JiraServiceProvider). Staff whose role only sees some tickets get
 * a 403 on the others.
 */
Route::middleware('can:view,ticket')->group(function () {
    Route::get('tickets/{ticket}/jira', [JiraIssueController::class, 'index'])->name('tickets.jira.index');

    Route::middleware(['permission:jira.manage_links', 'throttle:60,1'])->group(function () {
        Route::get('tickets/{ticket}/jira/search', [JiraIssueController::class, 'search'])->name('tickets.jira.search');
        Route::get('tickets/{ticket}/jira/projects', [JiraIssueController::class, 'projects'])->name('tickets.jira.projects');
        Route::get('tickets/{ticket}/jira/issue-types', [JiraIssueController::class, 'issueTypes'])->name('tickets.jira.issue-types');
        Route::post('tickets/{ticket}/jira', [JiraIssueController::class, 'store'])->name('tickets.jira.store');
        Route::post('tickets/{ticket}/jira/link', [JiraIssueController::class, 'link'])->name('tickets.jira.link');
        Route::delete('tickets/{ticket}/jira/{jiraIssueLink}', [JiraIssueController::class, 'destroy'])->name('tickets.jira.destroy');
    });
});
