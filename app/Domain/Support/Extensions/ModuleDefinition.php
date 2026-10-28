<?php

namespace App\Domain\Support\Extensions;

use App\Domain\Accounts\Support\PermissionDefinition;
use Closure;

/**
 * What a KiteDesk package says about itself so admins can switch it on and off (see
 * `KiteDesk::module()`). The scalar fields are plain data on purpose: a module catalogue can
 * describe a module in the same shape.
 */
final readonly class ModuleDefinition
{
    /**
     * @param  string  $id  stable identifier, lowercase letters, digits and dashes ("projects")
     * @param  string  $name  English name, translated where it is shown
     * @param  string  $description  English one-liner, translated where it is shown
     * @param  string  $icon  a lucide icon name for the Modules page
     * @param  list<string>  $requires  ids of modules that must be enabled first
     * @param  list<PermissionDefinition>  $permissions  listed on the role form only while the module is enabled
     * @param  (Closure(): void)|null  $onEnable  runs after the module is switched on
     * @param  (Closure(): void)|null  $onDisable  runs after the module is switched off
     */
    public function __construct(
        public string $id,
        public string $name,
        public string $description,
        public string $version,
        public string $vendor = 'KiteDesk',
        public string $icon = 'puzzle',
        public array $requires = [],
        public array $permissions = [],
        public ?Closure $onEnable = null,
        public ?Closure $onDisable = null,
    ) {}

    /**
     * @return array{id: string, name: string, description: string, version: string, vendor: string, icon: string, requires: list<string>}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'version' => $this->version,
            'vendor' => $this->vendor,
            'icon' => $this->icon,
            'requires' => $this->requires,
        ];
    }
}
