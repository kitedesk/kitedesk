<?php

namespace App\Domain\Webhooks\Support;

use App\Domain\Entitlements\Enums\Feature;
use App\Domain\Entitlements\Support\PlanLimits;
use App\Domain\Webhooks\Enums\WebhookEvent;
use App\Domain\Webhooks\Jobs\DeliverWebhook;
use App\Domain\Webhooks\Models\Webhook;
use Closure;
use Illuminate\Support\Str;

/**
 * Queues a delivery to every active webhook subscribed to an event. Nothing is sent while the
 * plan leaves webhooks out.
 */
class Webhooks
{
    public static function subscribed(WebhookEvent $event): bool
    {
        return PlanLimits::allows(Feature::Webhooks) && Webhook::query()->subscribedTo($event)->exists();
    }

    /**
     * @param  array<string, mixed>|Closure(): array<string, mixed>  $data  Built only when someone is subscribed.
     * @param  array<string, mixed>|null  $changes
     */
    public static function send(WebhookEvent $event, array|Closure $data, ?array $changes = null): void
    {
        if (! PlanLimits::allows(Feature::Webhooks)) {
            return;
        }

        $webhooks = Webhook::query()->subscribedTo($event)->get();

        if ($webhooks->isEmpty()) {
            return;
        }

        $data = $data instanceof Closure ? $data() : $data;

        foreach ($webhooks as $webhook) {
            $uuid = (string) Str::uuid();

            $delivery = $webhook->deliveries()->create([
                'uuid' => $uuid,
                'event' => $event->value,
                'payload' => WebhookPayload::make($uuid, $event->value, $data, $changes),
            ]);

            DeliverWebhook::dispatch($delivery);
        }
    }
}
