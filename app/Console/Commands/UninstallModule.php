<?php

namespace App\Console\Commands;

use App\Domain\Support\Extensions\ModuleException;
use App\Domain\Support\Extensions\ModuleRegistry;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('modules:uninstall {module : The module id} {--force : Do not ask for confirmation}')]
#[Description('Uninstall a module: roll its migrations back, which deletes its data')]
class UninstallModule extends Command
{
    public function handle(ModuleRegistry $modules): int
    {
        if (! $this->option('force') && ! $this->confirm("Uninstalling {$this->argument('module')} deletes all of its data. Continue?")) {
            return self::FAILURE;
        }

        try {
            $modules->uninstall($this->argument('module'));
        } catch (ModuleException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->components->info("Uninstalled {$this->argument('module')}.");

        return self::SUCCESS;
    }
}
