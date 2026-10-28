<?php

namespace App\Domain\Support\Extensions;

/**
 * Whether the installation may use a module at all. The open-source edition allows every
 * installed module; the hosted edition binds its own implementation to sell modules as plan
 * add-ons. A module that is not available cannot be enabled, and counts as disabled.
 */
interface ModuleAvailability
{
    public function isAvailable(ModuleDefinition $module): bool;
}
