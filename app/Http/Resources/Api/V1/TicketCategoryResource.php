<?php

namespace App\Http\Resources\Api\V1;

use App\Domain\Tickets\Models\TicketCategory;
use Illuminate\Http\Request;

/**
 * @mixin TicketCategory
 */
class TicketCategoryResource extends ApiResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'parent_id' => $this->parent_id,
            'name' => $this->name,
            'description' => $this->description,
            /** The form whose fields tickets in this category use; inherited from the parent when empty. */
            'form_id' => $this->ticket_form_id,
            'is_visible_to_customers' => $this->is_visible_to_customers,
            'is_active' => $this->is_active,
            'position' => $this->position,
            'updated_at' => self::time($this->updated_at),
        ];
    }
}
