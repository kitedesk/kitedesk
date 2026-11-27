<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Global fields show on every ticket, whatever its form, and carry their own "required" flag.
 * Any field can be limited to some ticket types.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('ticket_fields', function (Blueprint $table) {
            $table->boolean('is_global')->default(false)->after('options');
            $table->boolean('is_required')->default(false)->after('is_global');
            $table->json('ticket_types')->nullable()->after('is_required');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ticket_fields', function (Blueprint $table) {
            $table->dropColumn(['is_global', 'is_required', 'ticket_types']);
        });
    }
};
