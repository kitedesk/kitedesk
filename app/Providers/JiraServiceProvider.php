<?php

namespace App\Providers;

use App\Domain\Accounts\Support\PermissionDefinition;
use App\Domain\Jira\Console\RefreshJiraIssuesCommand;
use App\Domain\Jira\Console\RemindJiraTokenCommand;
use App\Domain\Jira\Listeners\SendNotesToJira;
use App\Domain\Jira\Listeners\UpdateTicketRemoteLinks;
use App\Domain\Jira\Support\JiraModule;
use App\Domain\Support\Extensions\KiteDesk;
use App\Domain\Support\Extensions\ModuleDefinition;
use App\Domain\Tickets\Events\MessageCreated;
use App\Domain\Tickets\Events\TicketUpdated;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * The Jira Cloud integration, shipped with core as an optional module. Admins install and
 * enable it under Admin → Modules; until then its tables don't exist and its routes answer 404.
 */
class JiraServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        KiteDesk::module(new ModuleDefinition(
            id: JiraModule::ID,
            name: 'Jira',
            description: 'Create and link Jira Cloud issues from tickets, and keep tickets up to date as the issues change.',
            version: '1.0.0',
            icon: 'square-kanban',
            permissions: [
                new PermissionDefinition(
                    name: 'jira.manage_links',
                    label: __('Create and link Jira issues'),
                    group: __('Tickets'),
                    description: __('Create Jira issues from tickets, and link or unlink existing ones.'),
                    grantToAgents: true,
                ),
            ],
            migrations: database_path('migrations/modules/jira'),
        ));

        KiteDesk::adminRoutes(base_path('routes/modules/jira/admin.php'), JiraModule::ID);
        KiteDesk::agentRoutes(base_path('routes/modules/jira/agent.php'), JiraModule::ID);
        Route::middleware('module:'.JiraModule::ID)->group(base_path('routes/modules/jira/webhooks.php'));

        Event::listen(MessageCreated::class, SendNotesToJira::class);
        Event::listen(TicketUpdated::class, UpdateTicketRemoteLinks::class);

        if ($this->app->runningInConsole()) {
            $this->commands([RefreshJiraIssuesCommand::class, RemindJiraTokenCommand::class]);
        }

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $schedule->command(RefreshJiraIssuesCommand::class)->everyFifteenMinutes()->withoutOverlapping()->onOneServer();
            $schedule->command(RemindJiraTokenCommand::class)->dailyAt('09:00')->onOneServer();
        });
    }
}
