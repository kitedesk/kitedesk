<?php

namespace App\Domain\Entitlements\Contracts;

use App\Domain\Entitlements\Enums\Feature;
use App\Domain\Entitlements\Enums\Limit;
use App\Domain\Entitlements\UnlimitedEntitlements;

/**
 * What the installation's plan includes. The open-source edition includes everything
 * ({@see UnlimitedEntitlements}); the hosted edition binds its own
 * implementation that reads the workspace's subscription.
 */
interface Entitlements
{
    public function allows(Feature $feature): bool;

    /**
     * The most the plan allows, or null for no limit.
     */
    public function limit(Limit $limit): ?int;
}
