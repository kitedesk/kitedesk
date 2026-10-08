<?php

use App\Domain\Webhooks\Models\WebhookDelivery;
use App\Domain\Workflows\Models\WorkflowRun;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('notifications:prune {--days=90}', function () {
    $deleted = 0;

    do {
        $ids = DB::table('notifications')
            ->whereNotNull('read_at')
            ->where('read_at', '<', now()->subDays((int) $this->option('days')))
            ->limit(1000)
            ->pluck('id');

        $deleted += DB::table('notifications')->whereIn('id', $ids)->delete();
    } while ($ids->isNotEmpty());

    $this->info("Deleted {$deleted} read notifications.");
})->purpose('Delete read notifications older than the given number of days');

Schedule::command('sla:check-breaches')->everyMinute()->withoutOverlapping()->onOneServer()->runInBackground();
Schedule::command('tickets:close-solved')->hourly()->withoutOverlapping()->onOneServer()->runInBackground();
Schedule::command('tickets:send-satisfaction-surveys')->everyFiveMinutes()->withoutOverlapping()->onOneServer()->runInBackground();
Schedule::command('mail:poll')->everyMinute()->withoutOverlapping()->onOneServer()->runInBackground();
Schedule::command('workflows:resume')->everyMinute()->withoutOverlapping()->onOneServer()->runInBackground();
Schedule::command('workflows:scan')->everyFiveMinutes()->withoutOverlapping()->onOneServer()->runInBackground();
Schedule::command('secrets:purge')->everyFiveMinutes()->withoutOverlapping()->onOneServer();

// Housekeeping: old audit entries, webhook logs, workflow runs, read notifications and failed jobs.
Schedule::command('activitylog:clean')->daily()->onOneServer();
Schedule::command('model:prune', ['--model' => [WebhookDelivery::class, WorkflowRun::class]])->daily()->onOneServer();
Schedule::command('notifications:prune')->daily()->onOneServer();
Schedule::command('passport:purge')->daily()->onOneServer();
Schedule::command('queue:prune-failed', ['--hours' => 720])->daily()->onOneServer();
