<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Webhooks\Actions\CreateWebhook;
use App\Domain\Webhooks\Actions\RedeliverWebhook;
use App\Domain\Webhooks\Models\Webhook;
use App\Domain\Webhooks\Models\WebhookDelivery;
use App\Http\Requests\Admin\SaveWebhookRequest;
use App\Http\Resources\Api\V1\WebhookDeliveryResource;
use App\Http\Resources\Api\V1\WebhookResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * Deliveries are signed: `X-Support-Signature` is `sha256=` followed by the HMAC-SHA256 of
 * `{X-Support-Timestamp}.{raw body}` with the webhook's secret. Failed deliveries are retried
 * up to 6 times over about 2.5 hours.
 *
 * @tags Webhooks
 */
class WebhookController extends ApiController
{
    /**
     * List webhooks.
     */
    public function index(): AnonymousResourceCollection
    {
        return WebhookResource::collection(Webhook::query()->orderBy('name')->orderBy('id')->get());
    }

    /**
     * Show a webhook.
     */
    public function show(Webhook $webhook): WebhookResource
    {
        return new WebhookResource($webhook);
    }

    /**
     * Create a webhook.
     *
     * The response includes the signing `secret`. It is shown only this once.
     */
    public function store(SaveWebhookRequest $request, CreateWebhook $createWebhook): JsonResponse
    {
        [$webhook, $secret] = $createWebhook->handle($request->validated());

        return (new WebhookResource($webhook))->additional(['secret' => $secret])->response()->setStatusCode(201);
    }

    /**
     * Update a webhook.
     *
     * Send the full webhook, as when creating.
     */
    public function update(SaveWebhookRequest $request, Webhook $webhook): WebhookResource
    {
        $webhook->update($request->validated());

        return new WebhookResource($webhook);
    }

    /**
     * Delete a webhook.
     */
    public function destroy(Webhook $webhook): Response
    {
        $webhook->delete();

        return response()->noContent();
    }

    /**
     * Rotate the signing secret.
     *
     * Returns the new `secret` once; the old one stops working right away.
     */
    public function rotateSecret(Webhook $webhook, CreateWebhook $createWebhook): JsonResponse
    {
        return response()->json(['secret' => $createWebhook->rotateSecret($webhook)]);
    }

    /**
     * List a webhook's deliveries.
     *
     * Newest first. Deliveries are kept for 30 days.
     */
    public function deliveries(Request $request, Webhook $webhook): AnonymousResourceCollection
    {
        return WebhookDeliveryResource::collection(
            $webhook->deliveries()->latest()->latest('id')->paginate($this->perPage($request))->withQueryString(),
        );
    }

    /**
     * Send a delivery again.
     *
     * Queues a new delivery with the same payload and a new ID.
     */
    public function redeliver(Webhook $webhook, WebhookDelivery $delivery, RedeliverWebhook $redeliver): JsonResponse
    {
        abort_unless($delivery->webhook_id === $webhook->id, 404);

        return (new WebhookDeliveryResource($redeliver->handle($delivery)))->response()->setStatusCode(202);
    }
}
