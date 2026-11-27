<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The Jira status id of each linked issue, so status rules can name exact Jira statuses.
 * Filled in by the next webhook or refresh.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jira_issue_links', function (Blueprint $table) {
            $table->string('status_id')->nullable()->after('issue_type');
        });
    }

    public function down(): void
    {
        Schema::table('jira_issue_links', function (Blueprint $table) {
            $table->dropColumn('status_id');
        });
    }
};
