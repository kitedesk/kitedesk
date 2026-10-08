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
        Schema::table('users', function (Blueprint $table) {
            $table->string('avatar_path')->nullable();
            $table->string('job_title')->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('locale', 10)->nullable();
            $table->text('signature')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->timestamp('deactivated_at')->nullable()->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['deactivated_at']);
            $table->dropColumn(['avatar_path', 'job_title', 'phone', 'locale', 'signature', 'last_login_at', 'deactivated_at']);
        });
    }
};
