<?php

namespace App\Console\Commands;

use App\Domain\Support\Extensions\ModuleException;
use App\Domain\Support\Extensions\ModuleRegistry;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('modules:enable {module : The module id}')]
#[Description('Switch an installed module on')]
class EnableModule extends Command
{
    public function handle(ModuleRegistry $modules): int
    {
        try {
            $modules->enable($this->argument('module'));
        } catch (ModuleException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->components->info("Enabled {$this->argument('module')}.");

        return self::SUCCESS;
    }
}
