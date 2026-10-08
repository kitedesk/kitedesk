<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Gives every ticket a custom status, starting with the default status of its category.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->foreignId('ticket_status_id')->nullable()->after('status')->constrained('ticket_statuses')->nullOnDelete();
        });

        DB::table('ticket_statuses')->where('is_default', true)->get(['id', 'category'])->each(function (object $status): void {
            DB::table('tickets')->where('status', $status->category)->update(['ticket_status_id' => $status->id]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('ticket_status_id');
        });
    }
};
