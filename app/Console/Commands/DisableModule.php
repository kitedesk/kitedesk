<?php

namespace App\Console\Commands;

use App\Domain\Support\Extensions\ModuleException;
use App\Domain\Support\Extensions\ModuleRegistry;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('modules:disable {module : The module id}')]
#[Description('Switch a module off, keeping its data')]
class DisableModule extends Command
{
    public function handle(ModuleRegistry $modules): int
    {
        try {
            $modules->disable($this->argument('module'));
        } catch (ModuleException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->components->info("Disabled {$this->argument('module')}.");

        return self::SUCCESS;
    }
}
