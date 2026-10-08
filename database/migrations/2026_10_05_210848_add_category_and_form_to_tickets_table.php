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
        Schema::table('tickets', function (Blueprint $table) {
            $table->foreignId('category_id')->nullable()->after('group_id')->constrained('ticket_categories')->nullOnDelete();
            $table->foreignId('ticket_form_id')->nullable()->after('category_id')->constrained()->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('ticket_form_id');
            $table->dropConstrainedForeignId('category_id');
        });
    }
};
