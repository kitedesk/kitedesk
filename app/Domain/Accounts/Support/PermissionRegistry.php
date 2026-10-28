<?php

namespace App\Domain\Accounts\Support;

use App\Domain\Accounts\Enums\Permission;
use App\Domain\Support\Extensions\ModuleRegistry;

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
     * The permissions in use: those of modules that are switched off are left out. They stay
     * stored (see {@see all()}), so roles keep them for when the module is enabled again.
     *
     * @return list<PermissionDefinition>
     */
    public function enabled(): array
    {
        $modules = app(ModuleRegistry::class);

        return array_values(array_filter(
            $this->all(),
            fn (PermissionDefinition $definition): bool => $definition->module === null || $modules->isEnabled($definition->module),
        ));
    }

    /**
     * @return list<string>
     */
    public function names(): array
    {
        return array_map(fn (PermissionDefinition $definition): string => $definition->name, $this->all());
    }
}
