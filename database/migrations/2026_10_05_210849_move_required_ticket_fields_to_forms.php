<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * "Required" now belongs to the form, not the field. Existing fields are collected into
 * a default form so tickets keep showing the same fields as before.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $fields = DB::table('ticket_fields')->orderBy('position')->orderBy('id')->get();

        if ($fields->isNotEmpty()) {
            $formId = DB::table('ticket_forms')->insertGetId([
                'name' => 'Default',
                'is_default' => true,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('ticket_form_field')->insert($fields->values()->map(fn (object $field, int $position): array => [
                'ticket_form_id' => $formId,
                'ticket_field_id' => $field->id,
                'position' => $position,
                'is_required' => (bool) $field->is_required,
            ])->all());

            DB::table('tickets')->update(['ticket_form_id' => $formId]);
        }

        Schema::table('ticket_fields', function (Blueprint $table) {
            $table->dropColumn('is_required');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ticket_fields', function (Blueprint $table) {
            $table->boolean('is_required')->default(false)->after('options');
        });

        foreach (DB::table('ticket_fields')->pluck('id') as $id) {
            DB::table('ticket_fields')->where('id', $id)->update([
                'is_required' => DB::table('ticket_form_field')->where('ticket_field_id', $id)->where('is_required', true)->exists(),
            ]);
        }
    }
};
