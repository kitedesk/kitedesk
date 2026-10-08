<?php

namespace App\Domain\Entitlements;

use App\Domain\Entitlements\Contracts\Entitlements;
use App\Domain\Entitlements\Enums\Feature;
use App\Domain\Entitlements\Enums\Limit;

/**
 * The open-source edition: every feature, no limits.
 */
class UnlimitedEntitlements implements Entitlements
{
    public function allows(Feature $feature): bool
    {
        return true;
    }

    public function limit(Limit $limit): ?int
    {
        return null;
    }
}
