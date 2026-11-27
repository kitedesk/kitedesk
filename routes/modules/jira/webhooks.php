<?php

use App\Http\Controllers\JiraWebhookController;
use Illuminate\Support\Facades\Route;

/*
 * Called by Jira, not browsers: no session, no CSRF token. Each delivery proves itself with
 * the X-Hub-Signature HMAC of the webhook secret. Only answering while the module is enabled.
 */
Route::post('webhooks/jira', JiraWebhookController::class)
    ->middleware('throttle:120,1')
    ->name('jira.webhook');
