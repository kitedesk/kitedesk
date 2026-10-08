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
        Schema::create('mailboxes', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('address')->unique();
            $table->boolean('is_default')->default(false);
            $table->foreignId('default_group_id')->nullable()->constrained('groups')->nullOnDelete();
            $table->foreignId('default_category_id')->nullable()->constrained('ticket_categories')->nullOnDelete();
            $table->string('driver')->default('imap');
            $table->string('imap_host')->nullable();
            $table->unsignedSmallInteger('imap_port')->nullable();
            $table->string('imap_encryption')->nullable();
            $table->string('imap_username')->nullable();
            $table->text('imap_password')->nullable();
            $table->string('imap_folder')->default('INBOX');
            $table->boolean('delete_after_import')->default(false);
            $table->text('inbound_secret')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_polled_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mailboxes');
    }
};
