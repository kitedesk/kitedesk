<?php

use App\Http\Controllers\Api\V1\ArticleController;
use App\Http\Controllers\Api\V1\GroupController;
use App\Http\Controllers\Api\V1\HelpCenterController;
use App\Http\Controllers\Api\V1\LookupController;
use App\Http\Controllers\Api\V1\OrganizationController;
use App\Http\Controllers\Api\V1\ReportController;
use App\Http\Controllers\Api\V1\RoleController;
use App\Http\Controllers\Api\V1\SatisfactionRatingController;
use App\Http\Controllers\Api\V1\TicketCollaboratorController;
use App\Http\Controllers\Api\V1\TicketController;
use App\Http\Controllers\Api\V1\TicketForwardController;
use App\Http\Controllers\Api\V1\TicketLinkController;
use App\Http\Controllers\Api\V1\TicketMergeController;
use App\Http\Controllers\Api\V1\TicketMessageController;
use App\Http\Controllers\Api\V1\TicketSetupController;
use App\Http\Controllers\Api\V1\TicketTagController;
use App\Http\Controllers\Api\V1\UserController;
use App\Http\Controllers\Api\V1\WebhookController;
use App\Http\Controllers\AttachmentController;
use App\Http\Middleware\Idempotent;
use Illuminate\Support\Facades\Route;

/*
 * REST API v1. Authenticate with a staff member's personal access token (Settings → API tokens).
 * Each endpoint requires a token ability, e.g. `tickets:read`, which the token owner's role must
 * also allow. POSTs accept an `Idempotency-Key` header so they can be retried safely.
 */
Route::prefix('v1')
    ->name('api.v1.')
    ->middleware(['auth:sanctum', 'active', 'staff', 'entitlement:api', 'throttle:api', Idempotent::class])
    ->group(function () {
        Route::middleware('api.ability:tickets:read')->group(function () {
            Route::get('tickets', [TicketController::class, 'index'])->name('tickets.index');
            Route::get('tickets/{ticket}', [TicketController::class, 'show'])->name('tickets.show');
            Route::get('tickets/{ticket}/messages', [TicketMessageController::class, 'index'])->name('tickets.messages.index');
            Route::get('tickets/{ticket}/links', [TicketLinkController::class, 'index'])->name('tickets.links.index');
            Route::get('attachments/{media}', AttachmentController::class)->name('attachments.show');
            Route::get('satisfaction-ratings', [SatisfactionRatingController::class, 'index'])->name('satisfaction-ratings.index');

            Route::get('groups', [LookupController::class, 'groups'])->name('groups.index');
            Route::get('statuses', [LookupController::class, 'statuses'])->name('statuses.index');
            Route::get('categories', [LookupController::class, 'categories'])->name('categories.index');
            Route::get('ticket-fields', [LookupController::class, 'fields'])->name('ticket-fields.index');
            Route::get('ticket-forms', [LookupController::class, 'forms'])->name('ticket-forms.index');
            Route::get('tags', [LookupController::class, 'tags'])->name('tags.index');
        });

        Route::middleware('api.ability:tickets:write')->group(function () {
            Route::post('tickets', [TicketController::class, 'store'])->name('tickets.store');
            Route::patch('tickets/{ticket}', [TicketController::class, 'update'])->name('tickets.update');
            Route::post('tickets/{ticket}/messages', [TicketMessageController::class, 'store'])->name('tickets.messages.store');
            Route::post('tickets/{ticket}/collaborators', [TicketCollaboratorController::class, 'store'])->name('tickets.collaborators.store');
            Route::delete('tickets/{ticket}/collaborators/{user}', [TicketCollaboratorController::class, 'destroy'])->name('tickets.collaborators.destroy');
            Route::put('tickets/{ticket}/tags/{tag}', [TicketTagController::class, 'update'])->name('tickets.tags.update');
            Route::delete('tickets/{ticket}/tags/{tag}', [TicketTagController::class, 'destroy'])->name('tickets.tags.destroy');
            Route::post('tickets/{ticket}/links', [TicketLinkController::class, 'store'])->name('tickets.links.store');
            Route::delete('tickets/{ticket}/links/{linked}', [TicketLinkController::class, 'destroy'])->name('tickets.links.destroy');
            Route::post('tickets/{ticket}/forward', TicketForwardController::class)->name('tickets.forward');
        });

        Route::post('tickets/{ticket}/merge', TicketMergeController::class)
            ->middleware('api.ability:tickets:merge')
            ->name('tickets.merge');

        Route::delete('tickets/{ticket}', [TicketController::class, 'destroy'])
            ->middleware('api.ability:tickets:delete')
            ->name('tickets.destroy');

        Route::middleware('api.ability:users:read')->group(function () {
            Route::get('users', [UserController::class, 'index'])->name('users.index');
            Route::get('users/{user}', [UserController::class, 'show'])->name('users.show');
            Route::get('organizations', [OrganizationController::class, 'index'])->name('organizations.index');
            Route::get('organizations/{organization}', [OrganizationController::class, 'show'])->name('organizations.show');
            Route::get('roles', [RoleController::class, 'index'])->name('roles.index');
        });

        Route::middleware('api.ability:users:write')->group(function () {
            Route::post('users', [UserController::class, 'store'])->name('users.store');
            Route::put('users/{user}', [UserController::class, 'update'])->name('users.update');
            Route::post('users/{user}/deactivate', [UserController::class, 'deactivate'])->name('users.deactivate');
            Route::post('users/{user}/reactivate', [UserController::class, 'reactivate'])->name('users.reactivate');
            Route::post('organizations', [OrganizationController::class, 'store'])->name('organizations.store');
            Route::put('organizations/{organization}', [OrganizationController::class, 'update'])->name('organizations.update');
            Route::delete('organizations/{organization}', [OrganizationController::class, 'destroy'])->name('organizations.destroy');
            Route::post('groups', [GroupController::class, 'store'])->name('groups.store');
            Route::put('groups/{group}', [GroupController::class, 'update'])->name('groups.update');
            Route::delete('groups/{group}', [GroupController::class, 'destroy'])->name('groups.destroy');
        });

        Route::middleware('api.ability:setup:write')->controller(TicketSetupController::class)->group(function () {
            Route::post('statuses', 'storeStatus')->name('statuses.store');
            Route::put('statuses/{ticket_status}', 'updateStatus')->name('statuses.update');
            Route::delete('statuses/{ticket_status}', 'destroyStatus')->name('statuses.destroy');
            Route::post('categories', 'storeCategory')->name('categories.store');
            Route::put('categories/{ticket_category}', 'updateCategory')->name('categories.update');
            Route::delete('categories/{ticket_category}', 'destroyCategory')->name('categories.destroy');
            Route::post('ticket-fields', 'storeField')->name('ticket-fields.store');
            Route::put('ticket-fields/{ticket_field}', 'updateField')->name('ticket-fields.update');
            Route::delete('ticket-fields/{ticket_field}', 'destroyField')->name('ticket-fields.destroy');
            Route::post('ticket-forms', 'storeForm')->name('ticket-forms.store');
            Route::put('ticket-forms/{ticket_form}', 'updateForm')->name('ticket-forms.update');
            Route::delete('ticket-forms/{ticket_form}', 'destroyForm')->name('ticket-forms.destroy');
        });

        Route::prefix('help-center')->name('help-center.')->group(function () {
            Route::middleware('api.ability:kb:read')->group(function () {
                Route::get('categories', [HelpCenterController::class, 'categories'])->name('categories.index');
                Route::get('sections', [HelpCenterController::class, 'sections'])->name('sections.index');
                Route::get('articles', [ArticleController::class, 'index'])->name('articles.index');
                Route::get('articles/{article}', [ArticleController::class, 'show'])->whereNumber('article')->name('articles.show');
            });

            Route::middleware('api.ability:kb:write')->group(function () {
                Route::post('categories', [HelpCenterController::class, 'storeCategory'])->name('categories.store');
                Route::put('categories/{category:id}', [HelpCenterController::class, 'updateCategory'])->name('categories.update');
                Route::delete('categories/{category:id}', [HelpCenterController::class, 'destroyCategory'])->name('categories.destroy');
                Route::post('sections', [HelpCenterController::class, 'storeSection'])->name('sections.store');
                Route::put('sections/{section}', [HelpCenterController::class, 'updateSection'])->name('sections.update');
                Route::delete('sections/{section}', [HelpCenterController::class, 'destroySection'])->name('sections.destroy');
                Route::post('articles', [ArticleController::class, 'store'])->name('articles.store');
                Route::put('articles/{article:id}', [ArticleController::class, 'update'])->name('articles.update');
                Route::post('articles/{article:id}/publish', [ArticleController::class, 'publish'])->name('articles.publish');
                Route::post('articles/{article:id}/unpublish', [ArticleController::class, 'unpublish'])->name('articles.unpublish');
                Route::delete('articles/{article:id}', [ArticleController::class, 'destroy'])->name('articles.destroy');
            });
        });

        Route::middleware(['api.ability:webhooks:manage', 'entitlement:webhooks'])->group(function () {
            Route::get('webhooks', [WebhookController::class, 'index'])->name('webhooks.index');
            Route::post('webhooks', [WebhookController::class, 'store'])->name('webhooks.store');
            Route::get('webhooks/{webhook}', [WebhookController::class, 'show'])->name('webhooks.show');
            Route::put('webhooks/{webhook}', [WebhookController::class, 'update'])->name('webhooks.update');
            Route::delete('webhooks/{webhook}', [WebhookController::class, 'destroy'])->name('webhooks.destroy');
            Route::post('webhooks/{webhook}/rotate-secret', [WebhookController::class, 'rotateSecret'])->name('webhooks.rotate-secret');
            Route::get('webhooks/{webhook}/deliveries', [WebhookController::class, 'deliveries'])->name('webhooks.deliveries.index');
            Route::post('webhooks/{webhook}/deliveries/{delivery}/redeliver', [WebhookController::class, 'redeliver'])->name('webhooks.deliveries.redeliver');
        });

        Route::get('reports/tickets', [ReportController::class, 'tickets'])
            ->middleware(['api.ability:reports:read', 'entitlement:reports'])
            ->name('reports.tickets');
    });
