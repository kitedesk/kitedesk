<?php

namespace App\Domain\Tickets\Actions\Setup;

use App\Domain\Tickets\Models\CustomStatus;

/**
 * Creates or updates an admin-defined status. New statuses go last in their category.
 */
class SaveCustomStatus
{
    /**
     * @param  array<string, mixed>  $attributes  Validated by `SaveCustomStatusRequest`.
     */
    public function create(array $attributes): CustomStatus
    {
        return CustomStatus::query()->create([
            ...$attributes,
            'position' => (int) CustomStatus::query()->where('category', $attributes['category'])->max('position') + 1,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes  Validated by `SaveCustomStatusRequest`.
     */
    public function update(CustomStatus $status, array $attributes): CustomStatus
    {
        $status->update($attributes);

        return $status;
    }
}
