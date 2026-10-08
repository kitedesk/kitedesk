<?php

namespace App\Domain\Accounts\Support;

use App\Domain\Accounts\Enums\Permission;
use App\Domain\Accounts\Enums\TicketAccess;
use App\Domain\Accounts\Models\Role;
use Spatie\Permission\Models\Permission as PermissionModel;
use Spatie\Permission\PermissionRegistrar;

/**
 * Keeps the stored permissions in step with the `PermissionRegistry` (the `Permission` enum
 * plus permissions registered by KiteDesk packages) and makes sure the built-in roles exist.
 * Safe to run repeatedly; call it from a migration whenever a permission is added so existing
 * installations pick it up. A permission seen for the first time is given to the built-in
 * Agent role when its definition says so.
 */
class RoleCatalog
{
    public const string ADMINISTRATOR = 'Administrator';

    public const string AGENT = 'Agent';

    public const string LIGHT_AGENT = 'Light agent';

    /**
     * Built-in roles, matching what admins, agents and light agents could do before roles
     * were configurable. Only the administrator's permissions are kept in step afterwards;
     * the others are seeded once and then belong to the admins.
     *
     * @var array<string, string>
     */
    public const array DESCRIPTIONS = [
        self::ADMINISTRATOR => 'Full access to every ticket and the whole admin center.',
        self::AGENT => 'Works and replies to tickets and sees reports.',
        self::LIGHT_AGENT => 'Sees tickets and adds internal notes, but cannot reply to customers.',
    ];

    public static function sync(): void
    {
        $registrar = app(PermissionRegistrar::class);
        $registrar->forgetCachedPermissions();

        $definitions = app(PermissionRegistry::class)->all();
        $names = array_map(fn (PermissionDefinition $definition): string => $definition->name, $definitions);
        $existing = PermissionModel::query()->where('guard_name', 'web')->pluck('name')->all();

        foreach ($names as $name) {
            PermissionModel::findOrCreate($name, 'web');
        }

        PermissionModel::query()->where('guard_name', 'web')->whereNotIn('name', $names)->delete();

        self::seedRole(self::ADMINISTRATOR, $names)->syncPermissions($names);
        $agentDefaults = array_map(
            fn (PermissionDefinition $definition): string => $definition->name,
            array_values(array_filter($definitions, fn (PermissionDefinition $definition): bool => $definition->grantToAgents)),
        );
        $agent = self::seedRole(self::AGENT, $agentDefaults);
        self::seedRole(self::LIGHT_AGENT, []);

        $newForAgents = array_values(array_diff($agentDefaults, $existing));

        if (! $agent->wasRecentlyCreated && $existing !== [] && $newForAgents !== []) {
            $agent->givePermissionTo($newForAgents);
        }

        $registrar->forgetCachedPermissions();
    }

    /**
     * @return list<Permission>
     */
    public static function agentPermissions(): array
    {
        return [
            Permission::CreateTickets,
            Permission::ReplyToTickets,
            Permission::UpdateTickets,
            Permission::MergeTickets,
            Permission::ForwardTickets,
            Permission::RunWorkflows,
            Permission::ReceiveAssignments,
            Permission::UseSecrets,
            Permission::UseAi,
            Permission::ViewReports,
            Permission::ExportReports,
        ];
    }

    public static function administrator(): Role
    {
        return Role::query()->where('guard_name', 'web')->where('name', self::ADMINISTRATOR)->where('is_system', true)->firstOrFail();
    }

    /**
     * Create a built-in role with its starting permissions, leaving an existing one as the
     * admins configured it.
     *
     * @param  list<string>  $permissions
     */
    private static function seedRole(string $name, array $permissions): Role
    {
        $role = Role::query()->where('guard_name', 'web')->where('name', $name)->first();

        if ($role !== null) {
            return $role;
        }

        $role = new Role(['name' => $name, 'guard_name' => 'web']);
        $role->forceFill([
            'description' => self::DESCRIPTIONS[$name],
            'ticket_access' => TicketAccess::All,
            'is_system' => true,
        ])->save();

        $role->syncPermissions($permissions);

        return $role;
    }
}
