<?php

namespace App\Http\Resources\Api\V1;

use App\Domain\Accounts\Models\Group;
use Illuminate\Http\Request;

/**
 * @mixin Group
 */
class GroupResource extends ApiResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'assignment_mode' => $this->assignment_mode->value,
            /** @var list<int> */
            'agent_ids' => $this->whenLoaded('agents', fn (): array => $this->agents->modelKeys()),
            'created_at' => self::time($this->created_at),
            'updated_at' => self::time($this->updated_at),
        ];
    }
}
