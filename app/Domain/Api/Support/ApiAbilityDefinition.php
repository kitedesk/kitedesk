<?php

namespace App\Domain\Api\Support;

use App\Domain\Accounts\Enums\Permission;
use App\Domain\Api\Enums\ApiAbility;
use App\Models\User;

/**
 * What a REST API token may be used for, as the registry knows it: the core `ApiAbility` cases
 * plus those KiteDesk packages register (see `KiteDesk::apiAbilities()`). A token never does more
 * than its owner, so each ability lists the permissions the owner's role must hold.
 */
final readonly class ApiAbilityDefinition
{
    /**
     * @param  list<string>  $permissions
     */
    public function __construct(
        public string $value,
        public string $label,
        public array $permissions = [],
        public ?string $module = null,
    ) {}

    public static function fromEnum(ApiAbility $ability): self
    {
        return new self(
            $ability->value,
            $ability->label(),
            array_map(fn (Permission $permission): string => $permission->value, $ability->permissions()),
        );
    }

    /**
     * Whether a token owned by this user may use the ability: active staff holding its permissions.
     */
    public function allowedFor(User $user): bool
    {
        return $user->isStaff()
            && ! $user->isDeactivated()
            && collect($this->permissions)->every(fn (string $permission): bool => $user->hasPermission($permission));
    }
}
