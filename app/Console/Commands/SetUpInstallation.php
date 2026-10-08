<?php

namespace App\Console\Commands;

use App\Domain\Support\Installation;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Laravel\Passport\Passport;

/**
 * Brings a new or upgraded installation up to date from its environment alone, so the Docker
 * image needs no manual steps. Safe to run on every start: each step skips what's already done.
 * What the environment doesn't say (the first administrator, the helpdesk's name) is asked on
 * the setup screen, whose link this prints.
 * Containers sharing the storage volume take turns, so only one of them migrates at a time.
 */
#[Signature('kitedesk:setup')]
#[Description('Prepare the installation: OAuth keys, database migrations and the first administrator')]
class SetUpInstallation extends Command
{
    public function handle(): int
    {
        if (blank(config('app.key'))) {
            $this->components->error('APP_KEY is not set. Generate one with `php artisan key:generate --show` and add it to the environment.');

            return self::FAILURE;
        }

        $lock = fopen(storage_path('framework/setup.lock'), 'c');

        if ($lock === false) {
            $this->components->error('storage/framework is not writable.');

            return self::FAILURE;
        }

        flock($lock, LOCK_EX);

        try {
            $ready = $this->createOAuthKeys()
                && $this->call('migrate', ['--force' => true]) === self::SUCCESS
                && $this->createFirstAdministrator();
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }

        if ($ready && Installation::needsSetup()) {
            $this->components->info('Finish setting up KiteDesk at this link:');
            $this->line('  '.Installation::setupUrl());
            $this->newLine();
        }

        return $ready ? self::SUCCESS : self::FAILURE;
    }

    /**
     * Keys for MCP clients' OAuth tokens, kept in storage/ unless the environment provides them.
     */
    private function createOAuthKeys(): bool
    {
        if (filled(config('passport.private_key')) || file_exists(Passport::keyPath('oauth-private.key'))) {
            return true;
        }

        return $this->call('passport:keys') === self::SUCCESS;
    }

    /**
     * The KITEDESK_ADMIN_* account, created only while the installation has no staff at all.
     */
    private function createFirstAdministrator(): bool
    {
        $admin = config('kitedesk.first_admin');

        if (blank($admin['email']) || Installation::hasStaff()) {
            return true;
        }

        if (blank($admin['password'])) {
            $this->components->error('Set KITEDESK_ADMIN_PASSWORD to create the first administrator.');

            return false;
        }

        return $this->call('kitedesk:create-admin', [
            '--name' => $admin['name'],
            '--email' => $admin['email'],
            '--password' => $admin['password'],
        ]) === self::SUCCESS;
    }
}
