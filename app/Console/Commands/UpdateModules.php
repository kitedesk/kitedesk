<?php

namespace App\Console\Commands;

use App\Domain\Support\Extensions\ModuleException;
use App\Domain\Support\Extensions\ModuleRegistry;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('modules:migrate')]
#[Description('Run the new migrations of installed modules that have been upgraded')]
class UpdateModules extends Command
{
    public function handle(ModuleRegistry $modules): int
    {
        try {
            $updated = $modules->updateInstalled();
        } catch (ModuleException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->components->info($updated === [] ? 'Every installed module is up to date.' : 'Updated '.implode(', ', $updated).'.');

        return self::SUCCESS;
    }
}
