<?php

namespace App\Http\Resources\Api\V1;

use App\Domain\Tickets\Models\Tag;
use Illuminate\Http\Request;

/**
 * @mixin Tag
 */
class TagResource extends ApiResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'tickets_count' => $this->whenCounted('tickets'),
        ];
    }
}
