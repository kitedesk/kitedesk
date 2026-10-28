<?php

use App\Domain\Accounts\Support\RoleCatalog;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\PermissionRegistrar;

/**
 * Adds the "modules" admin permission; only administrators get it.
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
        // RoleCatalog::sync() removes the permission once it's gone from the enum.
    }
};
