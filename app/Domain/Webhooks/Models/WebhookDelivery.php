<?php

namespace App\Domain\Webhooks\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $webhook_id
 * @property string $uuid
 * @property string $event
 * @property array<string, mixed> $payload
 * @property int|null $response_status
 * @property string|null $response_body
 * @property int $attempts
 * @property CarbonImmutable|null $delivered_at
 * @property CarbonImmutable|null $failed_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Webhook $webhook
 */
#[Fillable(['webhook_id', 'uuid', 'event', 'payload', 'response_status', 'response_body', 'attempts', 'delivered_at', 'failed_at'])]
class WebhookDelivery extends Model
{
    use Prunable;

    /**
     * Delivery logs are kept this long (`model:prune`).
     */
    public const int RETENTION_DAYS = 30;

    /**
     * @return Builder<static>
     */
    public function prunable(): Builder
    {
        return static::query()->where('created_at', '<', now()->subDays(self::RETENTION_DAYS));
    }

    /**
     * @return BelongsTo<Webhook, $this>
     */
    public function webhook(): BelongsTo
    {
        return $this->belongsTo(Webhook::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'delivered_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }
}
