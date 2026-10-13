<?php

use App\Domain\Accounts\Support\RoleCatalog;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\PermissionRegistrar;

/**
 * Adds the "security policy" and "audit log" admin permissions; only administrators get them.
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
        // RoleCatalog::sync() removes the permissions once they're gone from the enum.
    }
};
