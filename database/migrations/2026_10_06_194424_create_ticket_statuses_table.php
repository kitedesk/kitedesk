<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Statuses admins define on top of the fixed status categories. Each category starts with
 * one default status whose name is left empty so it shows the translated category name.
 */
return new class extends Migration
{
    /**
     * Default color of each category's built-in status.
     *
     * @var array<string, string>
     */
    private const array DEFAULTS = [
        'new' => 'amber',
        'open' => 'rose',
        'pending' => 'sky',
        'on_hold' => 'zinc',
        'solved' => 'emerald',
        'closed' => 'slate',
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('ticket_statuses', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('category');
            $table->string('color')->default('slate');
            $table->string('description')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['category', 'position']);
        });

        DB::table('ticket_statuses')->insert(collect(self::DEFAULTS)
            ->map(fn (string $color, string $category): array => [
                'category' => $category,
                'color' => $color,
                'position' => 0,
                'is_default' => true,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ])
            ->values()
            ->all());
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ticket_statuses');
    }
};
