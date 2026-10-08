<?php

namespace App\Domain\Tickets\Models;

use App\Domain\Secrets\Models\Secret;
use App\Domain\Tickets\Enums\TicketChannel;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Factories\TicketMessageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\Touches;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * @property int $id
 * @property int $ticket_id
 * @property int|null $author_id
 * @property string $body
 * @property bool $is_internal
 * @property TicketChannel $channel
 * @property array<string, mixed>|null $metadata
 * @property string|null $email_message_id Message-ID of the email this message arrived as.
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Ticket $ticket
 * @property-read User|null $author
 * @property-read Collection<int, Secret> $secrets
 */
#[Fillable(['ticket_id', 'author_id', 'body', 'is_internal', 'channel', 'metadata'])]
#[Touches(['ticket'])]
#[UseFactory(TicketMessageFactory::class)]
class TicketMessage extends Model implements HasMedia
{
    /** @use HasFactory<TicketMessageFactory> */
    use HasFactory, InteractsWithMedia;

    /**
     * @return BelongsTo<Ticket, $this>
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /**
     * Secret requests and shared secrets sent with this reply.
     *
     * @return HasMany<Secret, $this>
     */
    public function secrets(): HasMany
    {
        return $this->hasMany(Secret::class);
    }

    /**
     * Limit the query to messages the customer can see.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function public(Builder $query): void
    {
        $query->where('is_internal', false);
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('attachments')->useDisk(config('filesystems.default'));
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_internal' => 'boolean',
            'channel' => TicketChannel::class,
            'metadata' => 'array',
        ];
    }
}
