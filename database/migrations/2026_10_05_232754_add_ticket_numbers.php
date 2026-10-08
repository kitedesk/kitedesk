<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tickets get a display number generated from an admin-defined format. Existing tickets keep
 * their id as number, and the sequence continues after the highest id.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->json('value')->nullable();
            $table->timestamps();
        });

        Schema::create('ticket_number_sequences', function (Blueprint $table) {
            $table->string('scope')->primary();
            $table->unsignedBigInteger('next');
        });

        Schema::table('tickets', function (Blueprint $table) {
            $table->string('number', 40)->nullable()->unique()->after('id');
        });

        DB::table('tickets')->select('id')->orderBy('id')->chunkById(500, function ($tickets): void {
            foreach ($tickets as $ticket) {
                DB::table('tickets')->where('id', $ticket->id)->update(['number' => (string) $ticket->id]);
            }
        });

        DB::table('ticket_number_sequences')->insert([
            'scope' => 'all',
            'next' => (int) DB::table('tickets')->max('id') + 1,
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropUnique(['number']);
            $table->dropColumn('number');
        });

        Schema::dropIfExists('ticket_number_sequences');
        Schema::dropIfExists('settings');
    }
};
