<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * When the customer and the team last replied publicly, for time-based workflows
 * ("no customer reply for 3 days").
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->timestamp('last_customer_reply_at')->nullable();
            $table->timestamp('last_agent_reply_at')->nullable();

            $table->index(['status', 'last_customer_reply_at']);
            $table->index(['status', 'last_agent_reply_at']);
        });

        foreach (['last_customer_reply_at' => '=', 'last_agent_reply_at' => '<>'] as $column => $operator) {
            DB::table('tickets')->update([
                $column => DB::table('ticket_messages')
                    ->join('users', 'users.id', '=', 'ticket_messages.author_id')
                    ->whereColumn('ticket_messages.ticket_id', 'tickets.id')
                    ->where('ticket_messages.is_internal', false)
                    ->where('users.role', $operator, 'customer')
                    ->selectRaw('max(ticket_messages.created_at)'),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropIndex(['status', 'last_customer_reply_at']);
            $table->dropIndex(['status', 'last_agent_reply_at']);
            $table->dropColumn(['last_customer_reply_at', 'last_agent_reply_at']);
        });
    }
};
