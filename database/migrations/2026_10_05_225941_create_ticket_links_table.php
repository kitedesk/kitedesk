<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations. Links are symmetric: each one is stored in both directions.
     */
    public function up(): void
    {
        Schema::create('ticket_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('linked_ticket_id')->constrained('tickets')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['ticket_id', 'linked_ticket_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ticket_links');
    }
};
