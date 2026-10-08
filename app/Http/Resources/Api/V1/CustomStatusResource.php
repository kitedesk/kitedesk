<?php

namespace App\Http\Resources\Api\V1;

use App\Domain\Tickets\Models\CustomStatus;
use Illuminate\Http\Request;

/**
 * An admin-defined status. `category` is the built-in status it behaves as (`open`, `pending`, ...).
 *
 * @mixin CustomStatus
 */
class CustomStatusResource extends ApiResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->label(),
            'category' => $this->category->value,
            'color' => $this->color,
            'description' => $this->description,
            'position' => $this->position,
            'is_default' => $this->is_default,
            'is_active' => $this->is_active,
            'updated_at' => self::time($this->updated_at),
        ];
    }
}
