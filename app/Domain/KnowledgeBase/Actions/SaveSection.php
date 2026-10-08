<?php

namespace App\Domain\KnowledgeBase\Actions;

use App\Domain\KnowledgeBase\Models\Section;
use App\Domain\KnowledgeBase\Support\Slugs;
use Illuminate\Support\Str;

/**
 * Creates or updates a help center section. The slug comes from the name unless given, and is
 * unique within its category. New sections go last.
 */
class SaveSection
{
    /**
     * @param  array<string, mixed>  $attributes  Validated by `SaveSectionRequest`.
     */
    public function create(array $attributes): Section
    {
        $siblings = Section::query()->where('category_id', (int) $attributes['category_id']);

        return Section::query()->create([
            'category_id' => (int) $attributes['category_id'],
            'name' => (string) $attributes['name'],
            'slug' => Slugs::unique($siblings, Str::slug((string) (($attributes['slug'] ?? null) ?: $attributes['name']))),
            'description' => $attributes['description'] ?? null,
            'position' => (int) (clone $siblings)->max('position') + 1,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes  Validated by `SaveSectionRequest`.
     */
    public function update(Section $section, array $attributes): Section
    {
        $section->update([
            'name' => (string) $attributes['name'],
            'slug' => Slugs::unique(Section::query()->where('category_id', $section->category_id), Str::slug((string) (($attributes['slug'] ?? null) ?: $attributes['name'])), $section->id),
            'description' => $attributes['description'] ?? null,
        ]);

        return $section;
    }
}
