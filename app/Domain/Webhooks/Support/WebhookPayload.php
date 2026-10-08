<?php

namespace App\Domain\Webhooks\Support;

/**
 * The body every webhook delivery sends: `{id, event, occurred_at, data, changes?}`. `id` is the
 * delivery's UUID (also in `X-Support-Delivery`), so receivers can ignore repeats.
 */
class WebhookPayload
{
    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>|null  $changes
     * @return array<string, mixed>
     */
    public static function make(string $deliveryId, string $event, array $data, ?array $changes = null): array
    {
        return array_filter([
            'id' => $deliveryId,
            'event' => $event,
            'occurred_at' => now()->toIso8601ZuluString(),
            'data' => $data,
            'changes' => $changes,
        ], fn (mixed $value): bool => $value !== null);
    }
}
