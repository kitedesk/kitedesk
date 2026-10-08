<?php

namespace App\Http\Resources\Api\V1;

use App\Domain\KnowledgeBase\Models\Section;
use Illuminate\Http\Request;

/**
 * @mixin Section
 */
class SectionResource extends ApiResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'category_id' => $this->category_id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'position' => $this->position,
            'updated_at' => self::time($this->updated_at),
        ];
    }
}
