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
        Schema::create('workflows', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(false);
            $table->string('trigger');
            $table->json('graph');
            $table->unsignedInteger('max_runs_per_ticket')->default(1);
            $table->boolean('apply_to_existing')->default(false);
            $table->string('disabled_reason')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('last_run_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['is_active', 'trigger']);
        });

        Schema::create('workflow_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->string('status');
            $table->string('trigger_event');
            $table->json('context')->nullable();
            $table->json('graph');
            $table->string('current_node_id')->nullable();
            $table->timestamp('resume_at')->nullable();
            $table->unsignedTinyInteger('depth')->default(0);
            $table->foreignId('started_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['workflow_id', 'ticket_id']);
            $table->index(['status', 'resume_at']);
            $table->index(['ticket_id', 'status']);
            $table->index('created_at');
        });

        Schema::create('workflow_run_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_run_id')->constrained()->cascadeOnDelete();
            $table->string('node_id');
            $table->string('node_type');
            $table->string('status');
            $table->string('handle')->nullable();
            $table->json('output')->nullable();
            $table->unsignedInteger('iteration')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('executed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('workflow_run_steps');
        Schema::dropIfExists('workflow_runs');
        Schema::dropIfExists('workflows');
    }
};
