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
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->string('subject');
            $table->string('status')->default('new');
            $table->string('priority')->default('normal');
            $table->string('type')->nullable();
            $table->string('channel');
            $table->foreignId('requester_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('assignee_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('group_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('sla_policy_id')->nullable()->constrained()->nullOnDelete();
            $table->json('custom_fields')->nullable();

            $table->timestamp('first_response_due_at')->nullable();
            $table->timestamp('next_reply_due_at')->nullable();
            $table->timestamp('resolution_due_at')->nullable();
            $table->unsignedInteger('resolution_remaining_minutes')->nullable();
            $table->timestamp('first_responded_at')->nullable();
            $table->timestamp('sla_breached_at')->nullable();
            $table->timestamp('solved_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'assignee_id']);
            $table->index(['status', 'group_id']);
            $table->index('updated_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};
