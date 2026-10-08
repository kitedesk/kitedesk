<?php

namespace Database\Factories;

use App\Domain\Accounts\Enums\Permission;
use App\Domain\Accounts\Enums\TicketAccess;
use App\Domain\Accounts\Enums\UserType;
use App\Domain\Accounts\Models\Role;
use App\Domain\Accounts\Support\RoleCatalog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
            'type' => UserType::Customer,
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Indicate that an administrator deactivated the user.
     */
    public function deactivated(): static
    {
        return $this->state(fn (array $attributes) => [
            'deactivated_at' => now(),
            'is_available' => false,
        ]);
    }

    /**
     * Indicate that the model has two-factor authentication configured.
     */
    public function withTwoFactor(): static
    {
        return $this->state(fn (array $attributes) => [
            'two_factor_secret' => encrypt('secret'),
            'two_factor_recovery_codes' => encrypt(json_encode(['recovery-code-1'])),
            'two_factor_confirmed_at' => now(),
        ]);
    }

    /**
     * Indicate that the user is an administrator.
     */
    public function admin(): static
    {
        return $this->withRole(RoleCatalog::ADMINISTRATOR);
    }

    /**
     * Indicate that the user is an agent.
     */
    public function agent(): static
    {
        return $this->withRole(RoleCatalog::AGENT);
    }

    /**
     * Indicate that the user is a light agent (internal notes only).
     */
    public function lightAgent(): static
    {
        return $this->withRole(RoleCatalog::LIGHT_AGENT);
    }

    /**
     * A staff member holding the given role.
     */
    public function withRole(Role|string $role): static
    {
        return $this->state(fn (array $attributes) => ['type' => UserType::Staff])
            ->afterCreating(function (User $user) use ($role): void {
                if (is_string($role) && ! Role::query()->where('name', $role)->exists()) {
                    RoleCatalog::sync();
                }

                $user->assignStaffRole($role);
            });
    }

    /**
     * A staff member with a custom role granting only these permissions.
     *
     * @param  list<Permission>  $permissions
     */
    public function withPermissions(array $permissions, TicketAccess $ticketAccess = TicketAccess::All): static
    {
        return $this->state(fn (array $attributes) => ['type' => UserType::Staff])
            ->afterCreating(function (User $user) use ($permissions, $ticketAccess): void {
                RoleCatalog::sync();

                $role = new Role(['name' => 'Custom '.Str::random(8), 'ticket_access' => $ticketAccess]);
                $role->save();
                $role->syncPermissions(array_map(fn (Permission $permission): string => $permission->value, $permissions));

                $user->assignStaffRole($role);
            });
    }
}
