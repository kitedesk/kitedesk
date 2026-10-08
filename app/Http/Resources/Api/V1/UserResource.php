<?php

namespace App\Http\Resources\Api\V1;

use App\Models\User;
use Illuminate\Http\Request;

/**
 * @mixin User
 */
class UserResource extends ApiResource
{
    /**
     * @var list<string>
     */
    public const array RELATIONS = ['organization', 'roles', 'groups'];

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $this->resource->loadMissing(self::RELATIONS);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'type' => $this->type->value,
            /** Staff only. */
            'role' => self::named($this->staffRole()),
            'organization' => self::named($this->organization),
            /** Staff only: the groups whose tickets they work. */
            'groups' => $this->isStaff() ? $this->groups->map(fn ($group): ?array => self::named($group))->values()->all() : [],
            'timezone' => $this->timezone,
            'locale' => $this->locale,
            'job_title' => $this->job_title,
            'phone' => $this->phone,
            'avatar_url' => $this->avatar,
            'deactivated_at' => self::time($this->deactivated_at),
            'created_at' => self::time($this->created_at),
            'updated_at' => self::time($this->updated_at),
        ];
    }
}
