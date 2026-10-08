<?php

namespace App\Domain\Webhooks\Jobs;

use App\Domain\Support\PublicNetwork;
use App\Domain\Webhooks\Models\WebhookDelivery;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * POSTs a webhook payload signed with HMAC-SHA256.
 *
 * Receivers verify `X-Support-Signature` = "sha256=" . hmac_sha256("{timestamp}.{raw body}", secret),
 * using the value of `X-Support-Timestamp`, and should reject stale timestamps.
 *
 * The target is re-checked at send time and the connection pinned to the checked address, so a
 * URL whose DNS later points inside the network (rebinding) can't reach internal services.
 * Redirects are not followed.
 */
class DeliverWebhook implements ShouldQueue
{
    use Queueable;

    public int $tries = 6;

    public int $timeout = 30;

    public function __construct(public WebhookDelivery $delivery) {}

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [10, 60, 300, 1800, 7200];
    }

    public function handle(): void
    {
        $webhook = $this->delivery->webhook;

        if (! $webhook->is_active) {
            return;
        }

        $body = (string) json_encode($this->delivery->payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $timestamp = (string) now()->getTimestamp();

        $this->delivery->increment('attempts');

        $pinning = PublicNetwork::pinnedCurlOptions($webhook->url);

        if ($pinning === null) {
            // Not worth retrying: the address is internal until an admin changes the URL.
            $this->delivery->update([
                'response_status' => null,
                'response_body' => __('Blocked: the URL does not point to a public internet address.'),
                'failed_at' => now(),
            ]);

            return;
        }

        try {
            $response = Http::timeout(15)
                ->withOptions(['allow_redirects' => false, 'curl' => $pinning])
                ->withHeaders([
                    'User-Agent' => config('app.name').'-Webhooks/1.0',
                    'X-Support-Event' => $this->delivery->event,
                    'X-Support-Delivery' => $this->delivery->uuid,
                    'X-Support-Timestamp' => $timestamp,
                    'X-Support-Signature' => 'sha256='.hash_hmac('sha256', $timestamp.'.'.$body, $webhook->secret),
                ])
                ->withBody($body, 'application/json')
                ->post($webhook->url);
        } catch (ConnectionException $exception) {
            $this->delivery->update(['response_status' => null, 'response_body' => Str::limit($exception->getMessage(), 2000)]);

            throw $exception;
        }

        $this->delivery->update([
            'response_status' => $response->status(),
            'response_body' => Str::limit($response->body(), 2000),
            'delivered_at' => $response->successful() ? now() : null,
        ]);

        if (! $response->successful()) {
            throw new RuntimeException("Webhook delivery {$this->delivery->uuid} failed with HTTP {$response->status()}.");
        }
    }

    public function failed(?Throwable $exception): void
    {
        $this->delivery->update(['failed_at' => now()]);
    }
}
