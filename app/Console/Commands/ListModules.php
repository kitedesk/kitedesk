<?php

namespace App\Console\Commands;

use App\Domain\Support\Extensions\ModuleDefinition;
use App\Domain\Support\Extensions\ModuleRegistry;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('modules:list')]
#[Description('List the installed modules and whether they are enabled')]
class ListModules extends Command
{
    public function handle(ModuleRegistry $modules): int
    {
        $rows = collect($modules->all())->map(fn (ModuleDefinition $module): array => [
            $module->id,
            $module->name,
            $module->version,
            match (true) {
                ! $modules->isAvailable($module->id) => 'not available',
                $modules->isEnabled($module->id) => 'enabled',
                default => 'disabled',
            },
        ]);

        if ($rows->isEmpty()) {
            $this->components->info('No modules are installed.');

            return self::SUCCESS;
        }

        $this->table(['ID', 'Name', 'Version', 'Status'], $rows->all());

        return self::SUCCESS;
    }
}
