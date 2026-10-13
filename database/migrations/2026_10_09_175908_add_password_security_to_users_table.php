<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * When each password was last changed (for expiry), whether an admin asked for a new one, and
 * the previous passwords (hashes only) so the password policy can refuse reusing them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('password_changed_at')->nullable();
            $table->boolean('password_change_required')->default(false);
        });

        // Counting from today, so turning on expiry doesn't lock everyone out at once.
        DB::table('users')->update(['password_changed_at' => now()]);

        Schema::create('password_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('password');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('password_histories');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['password_changed_at', 'password_change_required']);
        });
    }
};
