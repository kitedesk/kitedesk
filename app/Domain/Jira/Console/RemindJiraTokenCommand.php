<?php

namespace App\Domain\Jira\Console;

use App\Domain\Jira\Notifications\JiraNeedsAttention;
use App\Domain\Jira\Support\JiraModule;
use App\Domain\Jira\Support\JiraSettings;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

/**
 * Jira API tokens expire (at most a year after they are made). Two weeks before the date the
 * admin entered, and again on the day, the people who manage integrations are reminded.
 */
class RemindJiraTokenCommand extends Command
{
    protected $signature = 'jira:remind-token';

    protected $description = 'Remind admins before the Jira API token expires';

    public function handle(): int
    {
        $expiresOn = JiraSettings::current()->expiresOn;

        if (! JiraModule::isEnabled() || $expiresOn === null) {
            return self::SUCCESS;
        }

        $daysLeft = (int) now()->startOfDay()->diffInDays($expiresOn->startOfDay(), false);

        if ($daysLeft === 14 || $daysLeft === 0) {
            Notification::send(JiraNeedsAttention::recipients(), new JiraNeedsAttention($daysLeft === 0
                ? __('The Jira API token expires today. Create a new one and save it in the Jira settings.')
                : __('The Jira API token expires in :days days. Create a new one and save it in the Jira settings.', ['days' => $daysLeft])));
        }

        return self::SUCCESS;
    }
}
