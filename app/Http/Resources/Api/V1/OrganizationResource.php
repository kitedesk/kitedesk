<?php

namespace App\Http\Resources\Api\V1;

use App\Domain\Accounts\Models\Organization;
use Illuminate\Http\Request;

/**
 * @mixin Organization
 */
class OrganizationResource extends ApiResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'domains' => $this->domains ?? [],
            'notes' => $this->notes,
            'members_count' => $this->whenCounted('members'),
            'created_at' => self::time($this->created_at),
            'updated_at' => self::time($this->updated_at),
        ];
    }
}
