<?php

namespace App\Domain\Support\Extensions;

/**
 * The open-source edition: every installed module can be enabled.
 */
class AllModulesAvailable implements ModuleAvailability
{
    public function isAvailable(ModuleDefinition $module): bool
    {
        return true;
    }
}
