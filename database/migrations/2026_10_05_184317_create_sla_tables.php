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
        Schema::create('business_schedules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('timezone');
            $table->json('hours');
            $table->timestamps();
        });

        Schema::create('holidays', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_schedule_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->date('date');
            $table->timestamps();

            $table->unique(['business_schedule_id', 'date']);
        });

        Schema::create('sla_policies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->foreignId('business_schedule_id')->nullable()->constrained()->nullOnDelete();
            $table->json('conditions')->nullable();
            $table->json('targets');
            $table->unsignedInteger('position')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sla_policies');
        Schema::dropIfExists('holidays');
        Schema::dropIfExists('business_schedules');
    }
};
