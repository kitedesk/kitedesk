<?php

namespace App\Http\Resources\Api\V1;

use App\Domain\Webhooks\Models\WebhookDelivery;
use Illuminate\Http\Request;

/**
 * @mixin WebhookDelivery
 */
class WebhookDeliveryResource extends ApiResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            /** Also the payload's `id` and the `X-Support-Delivery` header. */
            'uuid' => $this->uuid,
            'event' => $this->event,
            'payload' => $this->payload,
            'response_status' => $this->response_status,
            'response_body' => $this->response_body,
            'attempts' => $this->attempts,
            'delivered_at' => self::time($this->delivered_at),
            'failed_at' => self::time($this->failed_at),
            'created_at' => self::time($this->created_at),
        ];
    }
}
