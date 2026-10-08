<?php

use App\Domain\Accounts\Enums\Permission;
use App\Domain\Accounts\Models\Role;
use App\Domain\Accounts\Support\RoleCatalog;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\PermissionRegistrar;

/**
 * Adds the "use the AI assistant" permission and gives it to the built-in Agent role,
 * which already existed on installs created before the assistant.
 */
return new class extends Migration
{
    public function up(): void
    {
        RoleCatalog::sync();

        Role::query()->where('guard_name', 'web')->where('name', RoleCatalog::AGENT)->where('is_system', true)->first()
            ?->givePermissionTo(Permission::UseAi->value);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // RoleCatalog::sync() removes the permission once it's gone from the enum.
    }
};
