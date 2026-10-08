<?php

namespace App\Http\Resources\Api\V1;

use App\Domain\Webhooks\Models\Webhook;
use Illuminate\Http\Request;

/**
 * A webhook. Its signing secret is only returned when created or rotated.
 *
 * @mixin Webhook
 */
class WebhookResource extends ApiResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'url' => $this->url,
            /** @var list<string> */
            'events' => $this->events,
            'is_active' => $this->is_active,
            'created_at' => self::time($this->created_at),
            'updated_at' => self::time($this->updated_at),
        ];
    }
}
