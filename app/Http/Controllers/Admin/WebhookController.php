<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Support\EnumOptions;
use App\Domain\Webhooks\Actions\CreateWebhook;
use App\Domain\Webhooks\Actions\RedeliverWebhook;
use App\Domain\Webhooks\Enums\WebhookEvent;
use App\Domain\Webhooks\Jobs\DeliverWebhook;
use App\Domain\Webhooks\Models\Webhook;
use App\Domain\Webhooks\Models\WebhookDelivery;
use App\Domain\Webhooks\Support\WebhookPayload;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveWebhookRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class WebhookController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/webhooks/index', [
            'webhooks' => Webhook::query()
                ->withCount([
                    'deliveries as failed_deliveries_count' => fn ($query) => $query->whereNotNull('failed_at')->where('created_at', '>=', now()->subDay()),
                ])
                ->orderBy('name')
                ->get(['id', 'name', 'url', 'events', 'is_active', 'created_at']),
            'events' => EnumOptions::for(WebhookEvent::class),
        ]);
    }

    public function store(SaveWebhookRequest $request, CreateWebhook $createWebhook): RedirectResponse
    {
        [$webhook, $secret] = $createWebhook->handle($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Webhook created.')]);
        Inertia::flash('webhookSecret', $secret);

        return to_route('admin.webhooks.show', $webhook);
    }

    public function show(Webhook $webhook): Response
    {
        return Inertia::render('admin/webhooks/show', [
            'webhook' => $webhook->only(['id', 'name', 'url', 'events', 'is_active']),
            'deliveries' => $webhook->deliveries()
                ->latest()
                ->latest('id')
                ->limit(50)
                ->get()
                ->map(fn (WebhookDelivery $delivery): array => [
                    'id' => $delivery->id,
                    'uuid' => $delivery->uuid,
                    'event' => $delivery->event,
                    'payload' => $delivery->payload,
                    'response_status' => $delivery->response_status,
                    'response_body' => $delivery->response_body,
                    'attempts' => $delivery->attempts,
                    'delivered_at' => $delivery->delivered_at?->toIso8601String(),
                    'failed_at' => $delivery->failed_at?->toIso8601String(),
                    'created_at' => $delivery->created_at?->toIso8601String(),
                ]),
            'events' => EnumOptions::for(WebhookEvent::class),
        ]);
    }

    public function update(SaveWebhookRequest $request, Webhook $webhook): RedirectResponse
    {
        $webhook->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Webhook saved.')]);

        return back();
    }

    public function destroy(Webhook $webhook): RedirectResponse
    {
        $webhook->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Webhook deleted.')]);

        return to_route('admin.webhooks.index');
    }

    /**
     * Send a sample "ping" payload so integrators can verify their endpoint and signature check.
     */
    public function test(Webhook $webhook): RedirectResponse
    {
        $uuid = (string) Str::uuid();

        $delivery = $webhook->deliveries()->create([
            'uuid' => $uuid,
            'event' => 'ping',
            'payload' => WebhookPayload::make($uuid, 'ping', ['webhook_id' => $webhook->id]),
        ]);

        DeliverWebhook::dispatch($delivery);

        Inertia::flash('toast', ['type' => 'info', 'message' => __('Test delivery queued.')]);

        return back();
    }

    public function rotateSecret(Webhook $webhook, CreateWebhook $createWebhook): RedirectResponse
    {
        $secret = $createWebhook->rotateSecret($webhook);

        Inertia::flash('webhookSecret', $secret);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Signing secret rotated. Update your receiver.')]);

        return back();
    }

    public function redeliver(Webhook $webhook, WebhookDelivery $delivery, RedeliverWebhook $redeliver): RedirectResponse
    {
        abort_unless($delivery->webhook_id === $webhook->id, 404);

        $redeliver->handle($delivery);

        Inertia::flash('toast', ['type' => 'info', 'message' => __('Redelivery queued.')]);

        return back();
    }
}
