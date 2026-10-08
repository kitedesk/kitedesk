<?php

namespace App\Domain\Secrets\Actions;

use App\Domain\Secrets\Models\Secret;
use App\Models\User;

/**
 * Makes a secret unreadable right away, e.g. when a link went to the wrong person.
 */
class RevokeSecret
{
    public function handle(Secret $secret, User $agent): void
    {
        if ($secret->revoked_at !== null) {
            return;
        }

        $secret->forceFill(['revoked_at' => now()]);
        $secret->destroyContent();
        $secret->recordActivity('secret_revoked', $agent);
    }
}
