<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('groups', function (Blueprint $table) {
            $table->string('assignment_mode')->default('manual');
            $table->foreignId('last_assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_available')->default(true);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('groups', function (Blueprint $table) {
            $table->dropConstrainedForeignId('last_assigned_user_id');
            $table->dropColumn('assignment_mode');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_available');
        });
    }
};
