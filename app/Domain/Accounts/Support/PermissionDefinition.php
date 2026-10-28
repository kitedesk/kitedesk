<?php

namespace App\Domain\Accounts\Support;

use App\Domain\Accounts\Enums\Permission;

/**
 * A permission as listed on the role form: the core `Permission` cases plus any a KiteDesk
 * package registers (see `KiteDesk::permissions()`). Admin-center sections are named
 * `admin.*`, which is what lets someone with only that permission open the admin center.
 */
final readonly class PermissionDefinition
{
    public function __construct(
        public string $name,
        public string $label,
        public string $group,
        public string $description = '',
        public bool $grantToAgents = false,
        public ?string $module = null,
    ) {}

    /**
     * The same permission, owned by a module: it is only listed and checked while that module is enabled.
     */
    public function forModule(string $module): self
    {
        return new self($this->name, $this->label, $this->group, $this->description, $this->grantToAgents, $module);
    }

    public static function fromEnum(Permission $permission): self
    {
        return new self(
            name: $permission->value,
            label: $permission->label(),
            group: $permission->group(),
            description: $permission->description(),
            grantToAgents: in_array($permission, RoleCatalog::agentPermissions(), true),
        );
    }

    public function isAdminSection(): bool
    {
        return str_starts_with($this->name, 'admin.');
    }
}
