<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations. The content is encrypted with the secrets key before it is stored,
     * and wiped once the secret is used up, expires or is revoked.
     */
    public function up(): void
    {
        Schema::create('secrets', function (Blueprint $table) {
            $table->id();
            $table->string('token', 64)->unique();
            $table->string('kind');
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ticket_message_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('label');
            $table->text('ciphertext')->nullable();
            $table->unsignedTinyInteger('max_views');
            $table->unsignedTinyInteger('views')->default(0);
            $table->timestamp('expires_at')->index();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamp('destroyed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('secrets');
    }
};
