<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A customer's place in their organization (member or authorized contact), and who confirmed
 * it when. Everyone already in an organization was put there by an admin, so they start as members.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('organization_role')->nullable()->after('organization_id');
            $table->timestamp('organization_linked_at')->nullable()->after('organization_role');
            $table->foreignId('organization_linked_by')->nullable()->after('organization_linked_at')->constrained('users')->nullOnDelete();
        });

        DB::table('users')->whereNotNull('organization_id')->update(['organization_role' => 'member']);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('organization_linked_by');
            $table->dropColumn(['organization_role', 'organization_linked_at']);
        });
    }
};
