<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tickets linked to Jira issues, with what was last seen of each issue, and the comments
 * copied between a ticket and an issue (so none is copied twice or sent back).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jira_issue_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->string('issue_id');
            $table->string('issue_key');
            $table->string('summary')->default('');
            $table->string('issue_type')->nullable();
            $table->string('status_name')->nullable();
            $table->string('status_category')->nullable();
            $table->string('resolution')->nullable();
            $table->string('assignee_name')->nullable();
            $table->string('url');
            $table->timestamp('deleted_at_jira')->nullable();
            $table->foreignId('linked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->unique(['ticket_id', 'issue_id']);
            $table->index('issue_id');
        });

        Schema::create('jira_comment_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('jira_issue_link_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ticket_message_id')->nullable()->constrained()->nullOnDelete();
            $table->string('jira_comment_id');
            $table->string('direction');
            $table->timestamps();

            $table->unique(['jira_issue_link_id', 'jira_comment_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jira_comment_links');
        Schema::dropIfExists('jira_issue_links');
    }
};
