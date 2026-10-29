<?php

namespace App\Domain\Api\Support;

use App\Domain\Api\Enums\ApiAbility;
use App\Domain\Support\Extensions\ModuleRegistry;
use App\Models\User;

/**
 * Every API token ability the installation knows: the core `ApiAbility` cases first, then those
 * registered by KiteDesk packages. Abilities of a module that is switched off are not offered
 * and not honoured.
 */
class ApiAbilityRegistry
{
    /**
     * @var array<string, ApiAbilityDefinition>
     */
    private array $registered = [];

    public function register(ApiAbilityDefinition ...$definitions): void
    {
        foreach ($definitions as $definition) {
            $this->registered[$definition->value] = $definition;
        }
    }

    /**
     * An ability that may be used now: core, or from an enabled module.
     */
    public function find(string $value): ?ApiAbilityDefinition
    {
        $ability = ApiAbility::tryFrom($value);

        if ($ability !== null) {
            return ApiAbilityDefinition::fromEnum($ability);
        }

        $definition = $this->registered[$value] ?? null;

        return $definition !== null && ($definition->module === null || app(ModuleRegistry::class)->isEnabled($definition->module))
            ? $definition
            : null;
    }

    /**
     * @return list<ApiAbilityDefinition>
     */
    public function all(): array
    {
        $core = array_map(ApiAbilityDefinition::fromEnum(...), ApiAbility::cases());
        $modules = app(ModuleRegistry::class);

        return [
            ...$core,
            ...array_values(array_filter(
                $this->registered,
                fn (ApiAbilityDefinition $definition): bool => ! in_array($definition->value, array_column($core, 'value'), true)
                    && ($definition->module === null || $modules->isEnabled($definition->module)),
            )),
        ];
    }

    /**
     * @return list<ApiAbilityDefinition>
     */
    public function availableTo(User $user): array
    {
        return array_values(array_filter($this->all(), fn (ApiAbilityDefinition $definition): bool => $definition->allowedFor($user)));
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public function options(): array
    {
        return array_map(fn (ApiAbilityDefinition $definition): array => ['value' => $definition->value, 'label' => $definition->label], $this->all());
    }
}
