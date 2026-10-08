<?php

namespace App\Domain\Webhooks\Actions;

use App\Domain\Webhooks\Jobs\DeliverWebhook;
use App\Domain\Webhooks\Models\WebhookDelivery;
use Illuminate\Support\Str;

/**
 * Sends a past delivery's payload again as a new delivery, with its own ID.
 */
class RedeliverWebhook
{
    public function handle(WebhookDelivery $delivery): WebhookDelivery
    {
        $uuid = (string) Str::uuid();

        $copy = $delivery->webhook->deliveries()->create([
            'uuid' => $uuid,
            'event' => $delivery->event,
            'payload' => [...$delivery->payload, 'id' => $uuid],
        ]);

        DeliverWebhook::dispatch($copy);

        return $copy;
    }
}
