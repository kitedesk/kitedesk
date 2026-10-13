<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Activity logged before the audit log was all under "default": file it under the category the
 * audit log filters by, from what it was about.
 */
return new class extends Migration
{
    public function up(): void
    {
        $categories = [
            'tickets' => ['App\Domain\Tickets\Models\Ticket', 'App\Domain\Tickets\Models\TicketMessage', 'App\Domain\Secrets\Models\Secret', 'App\Domain\Tickets\Models\SatisfactionRating'],
            'users' => ['App\Models\User'],
        ];

        foreach ($categories as $category => $types) {
            DB::table('activity_log')->where('log_name', 'default')->whereIn('subject_type', $types)->update(['log_name' => $category]);
        }
    }

    public function down(): void
    {
        DB::table('activity_log')->whereIn('log_name', ['tickets', 'users'])->update(['log_name' => 'default']);
    }
};
