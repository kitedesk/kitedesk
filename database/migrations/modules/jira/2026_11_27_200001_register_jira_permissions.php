<?php

use App\Domain\Accounts\Support\RoleCatalog;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\PermissionRegistrar;

/**
 * Stores the module's permissions and gives them to the built-in roles. Only the module's
 * default grants are applied; admins change them on the role form once the module is enabled.
 */
return new class extends Migration
{
    public function up(): void
    {
        RoleCatalog::sync();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // RoleCatalog::sync() removes the permissions once the module is gone.
    }
};
