<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * How a saved view shows its tickets: `list` or `board`; null follows the agent's preference.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('ticket_views', function (Blueprint $table) {
            $table->string('layout')->nullable()->after('sort');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ticket_views', function (Blueprint $table) {
            $table->dropColumn('layout');
        });
    }
};
