<?php

namespace App\Domain\Webhooks\Models;

use App\Domain\Webhooks\Enums\WebhookEvent;
use Carbon\CarbonImmutable;
use Database\Factories\WebhookFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property string $url
 * @property string $secret
 * @property list<string> $events
 * @property bool $is_active
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Collection<int, WebhookDelivery> $deliveries
 */
#[Fillable(['name', 'url', 'secret', 'events', 'is_active'])]
#[Hidden(['secret'])]
#[UseFactory(WebhookFactory::class)]
class Webhook extends Model
{
    /** @use HasFactory<WebhookFactory> */
    use HasFactory;

    /**
     * @return HasMany<WebhookDelivery, $this>
     */
    public function deliveries(): HasMany
    {
        return $this->hasMany(WebhookDelivery::class);
    }

    /**
     * Active webhooks subscribed to the given event.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function subscribedTo(Builder $query, WebhookEvent $event): void
    {
        $query->where('is_active', true)->whereJsonContains('events', $event->value);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'secret' => 'encrypted',
            'events' => 'array',
            'is_active' => 'boolean',
        ];
    }
}
