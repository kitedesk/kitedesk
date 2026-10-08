<?php

use App\Http\Controllers\Admin\EmailTemplateController;
use App\Http\Controllers\Admin\MailboxController;
use Illuminate\Support\Facades\Route;

Route::middleware('permission:admin.email')->group(function () {
    Route::resource('mailboxes', MailboxController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::post('mailboxes/{mailbox}/test', [MailboxController::class, 'test'])->name('mailboxes.test');

    Route::get('email-templates', [EmailTemplateController::class, 'index'])->name('email-templates.index');
    Route::get('email-templates/{event}/edit', [EmailTemplateController::class, 'edit'])->name('email-templates.edit');
    Route::put('email-templates/{event}', [EmailTemplateController::class, 'update'])->name('email-templates.update');
    Route::delete('email-templates/{event}', [EmailTemplateController::class, 'destroy'])->name('email-templates.destroy');
});
