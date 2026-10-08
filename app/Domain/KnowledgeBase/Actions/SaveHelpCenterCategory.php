<?php

namespace App\Domain\KnowledgeBase\Actions;

use App\Domain\KnowledgeBase\Models\Category;
use App\Domain\KnowledgeBase\Support\Slugs;
use Illuminate\Support\Str;

/**
 * Creates or updates a help center category. The slug comes from the name unless given, and is
 * made unique. New categories go last.
 */
class SaveHelpCenterCategory
{
    /**
     * @param  array<string, mixed>  $attributes  Validated by `SaveCategoryRequest`.
     */
    public function create(array $attributes): Category
    {
        return Category::query()->create([
            'name' => (string) $attributes['name'],
            'slug' => Slugs::unique(Category::query(), Str::slug((string) (($attributes['slug'] ?? null) ?: $attributes['name']))),
            'description' => $attributes['description'] ?? null,
            'position' => (int) Category::query()->max('position') + 1,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes  Validated by `SaveCategoryRequest`.
     */
    public function update(Category $category, array $attributes): Category
    {
        $category->update([
            'name' => (string) $attributes['name'],
            'slug' => Slugs::unique(Category::query(), Str::slug((string) (($attributes['slug'] ?? null) ?: $attributes['name'])), $category->id),
            'description' => $attributes['description'] ?? null,
        ]);

        return $category;
    }
}
