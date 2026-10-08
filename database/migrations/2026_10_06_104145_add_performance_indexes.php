<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Indexes for the queries the app actually runs: agent views and sidebar counts, the portal,
 * SLA checks, reports, unread notifications and relationship lookups.
 *
 * Foreign key columns get their own index everywhere except MySQL/MariaDB, which already
 * create one for every foreign key (Postgres and SQLite don't).
 */
return new class extends Migration
{
    /**
     * table => list of indexes (each a list of columns).
     *
     * @var array<string, list<list<string>>>
     */
    private const array INDEXES = [
        'tickets' => [
            ['status', 'updated_at'],
            ['assignee_id', 'status', 'updated_at'],
            ['requester_id', 'status', 'updated_at'],
            ['requester_id', 'created_at'],
            ['status', 'solved_at'],
            ['created_at'],
            ['solved_at'],
            ['first_response_due_at'],
            ['next_reply_due_at'],
            ['resolution_due_at'],
        ],
        'notifications' => [
            ['notifiable_type', 'notifiable_id', 'read_at'],
        ],
    ];

    /**
     * Foreign keys that are looked up on their own.
     *
     * @var array<string, list<string>>
     */
    private const array FOREIGN_KEYS = [
        'tickets' => ['group_id', 'organization_id', 'category_id', 'ticket_form_id', 'sla_policy_id', 'merged_into_id', 'mailbox_id'],
        'ticket_messages' => ['author_id'],
        'tag_ticket' => ['ticket_id'],
        'group_user' => ['user_id'],
        'ticket_collaborators' => ['user_id'],
        'ticket_links' => ['linked_ticket_id'],
        'canned_responses' => ['user_id', 'group_id'],
        'ticket_views' => ['user_id'],
        'users' => ['organization_id'],
        'kb_articles' => ['section_id', 'author_id'],
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach ($this->indexes() as $table => $indexes) {
            Schema::table($table, function (Blueprint $blueprint) use ($indexes): void {
                foreach ($indexes as $columns) {
                    $blueprint->index($columns);
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach ($this->indexes() as $table => $indexes) {
            Schema::table($table, function (Blueprint $blueprint) use ($indexes): void {
                foreach ($indexes as $columns) {
                    $blueprint->dropIndex($columns);
                }
            });
        }
    }

    /**
     * @return array<string, list<list<string>>>
     */
    private function indexes(): array
    {
        $indexes = self::INDEXES;

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            return $indexes;
        }

        foreach (self::FOREIGN_KEYS as $table => $columns) {
            foreach ($columns as $column) {
                $indexes[$table][] = [$column];
            }
        }

        return $indexes;
    }
};
