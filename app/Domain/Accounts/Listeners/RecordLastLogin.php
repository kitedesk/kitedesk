<?php

namespace App\Domain\Accounts\Listeners;

use App\Models\User;
use Illuminate\Auth\Events\Login;

/**
 * Remembers when people last signed in, shown to administrators on the user page.
 */
class RecordLastLogin
{
    public function handle(Login $event): void
    {
        if ($event->user instanceof User) {
            $event->user->forceFill(['last_login_at' => now()])->saveQuietly();
        }
    }
}
