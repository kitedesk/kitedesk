<?php

namespace App\Domain\Tickets\Actions\Setup;

use App\Domain\Tickets\Models\TicketCategory;

/**
 * Creates or updates a ticket category. New categories go last among their siblings.
 */
class SaveTicketCategory
{
    /**
     * @param  array<string, mixed>  $attributes  From `SaveTicketCategoryRequest::categoryAttributes()`.
     */
    public function create(array $attributes): TicketCategory
    {
        return TicketCategory::query()->create([
            ...$attributes,
            'position' => (int) TicketCategory::query()->where('parent_id', $attributes['parent_id'] ?? null)->max('position') + 1,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes  From `SaveTicketCategoryRequest::categoryAttributes()`.
     */
    public function update(TicketCategory $category, array $attributes): TicketCategory
    {
        $category->update($attributes);

        return $category;
    }
}
