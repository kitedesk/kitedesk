<?php

namespace App\Domain\Accounts\Support;

use App\Domain\Accounts\Enums\Permission;

/**
 * Every permission the installation knows: the core `Permission` cases first, then those
 * registered by KiteDesk packages. `RoleCatalog::sync()` stores exactly these, and the role
 * form, `auth.can` and the admin-center check read them from here.
 */
class PermissionRegistry
{
    /**
     * @var array<string, PermissionDefinition>
     */
    private array $registered = [];

    public function register(PermissionDefinition ...$definitions): void
    {
        foreach ($definitions as $definition) {
            $this->registered[$definition->name] = $definition;
        }
    }

    /**
     * @return list<PermissionDefinition>
     */
    public function all(): array
    {
        $core = array_map(PermissionDefinition::fromEnum(...), Permission::cases());
        $coreNames = Permission::values();

        return [
            ...$core,
            ...array_values(array_filter($this->registered, fn (PermissionDefinition $definition): bool => ! in_array($definition->name, $coreNames, true))),
        ];
    }

    /**
     * @return list<string>
     */
    public function names(): array
    {
        return array_map(fn (PermissionDefinition $definition): string => $definition->name, $this->all());
    }
}
