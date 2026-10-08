<?php

namespace App\Http\Resources\Api\V1;

use App\Domain\KnowledgeBase\Models\Category;
use Illuminate\Http\Request;

/**
 * @mixin Category
 */
class HelpCenterCategoryResource extends ApiResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'position' => $this->position,
            'updated_at' => self::time($this->updated_at),
        ];
    }
}
