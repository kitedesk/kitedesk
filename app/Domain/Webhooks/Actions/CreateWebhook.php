<?php

namespace App\Domain\Webhooks\Actions;

use App\Domain\Webhooks\Models\Webhook;
use Illuminate\Support\Str;

/**
 * Creates a webhook with a new signing secret, which the caller shows once.
 */
class CreateWebhook
{
    /**
     * @param  array<string, mixed>  $attributes  Validated by `SaveWebhookRequest`.
     * @return array{Webhook, string} The webhook and its plain-text secret.
     */
    public function handle(array $attributes): array
    {
        $secret = Str::random(40);

        return [Webhook::query()->create([...$attributes, 'secret' => $secret]), $secret];
    }

    /**
     * Replace the signing secret; receivers must switch to the returned one.
     */
    public function rotateSecret(Webhook $webhook): string
    {
        $secret = Str::random(40);
        $webhook->update(['secret' => $secret]);

        return $secret;
    }
}
