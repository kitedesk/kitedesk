<?php

namespace App\Console\Commands;

use App\Domain\Secrets\Models\Secret;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('secrets:purge')]
#[Description('Wipe the encrypted content of secrets that have expired')]
class PurgeExpiredSecrets extends Command
{
    public function handle(): int
    {
        $purged = 0;

        Secret::query()
            ->expiredWithContent()
            ->with('ticket')
            ->chunkById(200, function ($secrets) use (&$purged): void {
                foreach ($secrets as $secret) {
                    $secret->destroyContent();
                    $secret->recordActivity('secret_expired');
                    $purged++;
                }
            });

        $this->components->info("Purged {$purged} expired secret(s).");

        return self::SUCCESS;
    }
}
