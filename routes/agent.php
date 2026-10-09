<?php

use App\Http\Controllers\Agent\AvailabilityController;
use App\Http\Controllers\Agent\BoardPreferencesController;
use App\Http\Controllers\Agent\BulkTicketController;
use App\Http\Controllers\Agent\CannedResponseController;
use App\Http\Controllers\Agent\InternalRequestController;
use App\Http\Controllers\Agent\NotificationController;
use App\Http\Controllers\Agent\ReportController;
use App\Http\Controllers\Agent\SavedViewController;
use App\Http\Controllers\Agent\SearchController;
use App\Http\Controllers\Agent\TicketAiController;
use App\Http\Controllers\Agent\TicketCollaboratorController;
use App\Http\Controllers\Agent\TicketController;
use App\Http\Controllers\Agent\TicketForwardController;
use App\Http\Controllers\Agent\TicketLinkController;
use App\Http\Controllers\Agent\TicketMergeController;
use App\Http\Controllers\Agent\TicketMessageController;
use App\Http\Controllers\Agent\TicketSecretController;
use App\Http\Controllers\Agent\TicketWorkflowController;
use App\Http\Controllers\AttachmentController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('attachments/{media}', AttachmentController::class)->name('attachments.show');
});

Route::prefix('agent')
    ->name('agent.')
    ->middleware(['auth', 'verified', 'staff'])
    ->group(function () {
        Route::redirect('/', '/agent/tickets')->name('home');

        Route::patch('tickets/bulk', [BulkTicketController::class, 'update'])->name('tickets.bulk');
        Route::get('tickets/board/lane', [TicketController::class, 'boardLane'])->name('tickets.board.lane');
        Route::resource('tickets', TicketController::class)->only(['index', 'create', 'store']);

        // Staff whose role only sees some tickets get a 403 on the others.
        Route::middleware('can:view,ticket')->group(function () {
            Route::resource('tickets', TicketController::class)->only(['show', 'update', 'destroy']);
            Route::post('tickets/{ticket}/messages', [TicketMessageController::class, 'store'])->name('tickets.messages.store');
            Route::post('tickets/{ticket}/collaborators', [TicketCollaboratorController::class, 'store'])->name('tickets.collaborators.store');
            Route::delete('tickets/{ticket}/collaborators/{user}', [TicketCollaboratorController::class, 'destroy'])->name('tickets.collaborators.destroy');
            Route::post('tickets/{ticket}/merge', TicketMergeController::class)->name('tickets.merge');
            Route::post('tickets/{ticket}/forward', TicketForwardController::class)->name('tickets.forward');
            Route::post('tickets/{ticket}/links', [TicketLinkController::class, 'store'])->name('tickets.links.store');
            Route::delete('tickets/{ticket}/links/{linked}', [TicketLinkController::class, 'destroy'])->name('tickets.links.destroy');
            Route::post('tickets/{ticket}/workflows/{workflow}', [TicketWorkflowController::class, 'store'])->middleware('entitlement:workflows')->name('tickets.workflows.store');
            Route::post('tickets/{ticket}/secrets', [TicketSecretController::class, 'store'])->name('tickets.secrets.store');

            Route::middleware(['can:useAi,ticket', 'throttle:ai'])->prefix('tickets/{ticket}/ai')->name('tickets.ai.')->group(function () {
                Route::get('summary', [TicketAiController::class, 'summary'])->name('summary');
                Route::get('articles', [TicketAiController::class, 'articles'])->name('articles');
                Route::post('draft', [TicketAiController::class, 'draft'])->name('draft');
                Route::post('improve', [TicketAiController::class, 'improve'])->name('improve');
            });
        });

        // Requests an agent opens for another department. Opening new ones comes with the plan;
        // following and answering existing ones never stops.
        Route::get('requests', [InternalRequestController::class, 'index'])->name('requests.index');
        Route::middleware('entitlement:internal_requests')->group(function () {
            Route::get('requests/create', [InternalRequestController::class, 'create'])->name('requests.create');
            Route::post('requests', [InternalRequestController::class, 'store'])->name('requests.store');
        });
        Route::middleware('can:view,ticket')->group(function () {
            Route::get('requests/{ticket}', [InternalRequestController::class, 'show'])->name('requests.show');
            Route::post('requests/{ticket}/replies', [InternalRequestController::class, 'reply'])->name('requests.replies.store');
        });

        Route::post('secrets/{secret}/reveal', [TicketSecretController::class, 'reveal'])->middleware('throttle:30,1')->name('secrets.reveal');
        Route::delete('secrets/{secret}', [TicketSecretController::class, 'destroy'])->name('secrets.destroy');

        Route::resource('canned-responses', CannedResponseController::class)
            ->parameters(['canned-responses' => 'cannedResponse'])
            ->only(['index', 'store', 'update', 'destroy']);

        Route::patch('availability', AvailabilityController::class)->name('availability');
        Route::patch('preferences/board', BoardPreferencesController::class)->name('preferences.board');

        Route::post('views', [SavedViewController::class, 'store'])->name('views.store');
        Route::patch('views/{view}', [SavedViewController::class, 'update'])->name('views.update');
        Route::delete('views/{view}', [SavedViewController::class, 'destroy'])->name('views.destroy');
        Route::post('views/{view}/move', [SavedViewController::class, 'move'])->name('views.move');

        Route::middleware('entitlement:reports')->group(function () {
            Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
            Route::get('reports/export', [ReportController::class, 'export'])->name('reports.export');
        });

        Route::get('search', SearchController::class)->name('search');

        Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
        Route::post('notifications/read', [NotificationController::class, 'markAllRead'])->name('notifications.read');
    });
