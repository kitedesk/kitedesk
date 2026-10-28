<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A module is installed (its database changes are in place) before it can be enabled. Existing
 * rows start as not installed, whatever they said before: nothing here can tell whether a
 * module's tables exist, and installing a module whose migrations already ran is harmless.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('module_states', function (Blueprint $table) {
            $table->boolean('installed')->default(false)->after('id');
            $table->timestamp('installed_at')->nullable()->after('enabled_by');
        });
    }

    public function down(): void
    {
        Schema::table('module_states', function (Blueprint $table) {
            $table->dropColumn(['installed', 'installed_at']);
        });
    }
};
