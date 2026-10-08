<?php

namespace App\Domain\Secrets\Models;

use App\Domain\Secrets\Enums\SecretKind;
use App\Domain\Secrets\Enums\SecretStatus;
use App\Domain\Secrets\Policies\SecretPolicy;
use App\Domain\Tickets\Models\Ticket;
use App\Domain\Tickets\Models\TicketMessage;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Factories\SecretFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A password or other secret exchanged with a customer on a ticket:
 *
 * - a request is answered by the requester or someone copied, and read by agents;
 * - a share is written by an agent and read by the requester or someone copied.
 *
 * Everyone involved has to be signed in. The content is encrypted with the secrets key
 * (`SecretVault`) and wiped once the secret is used up, expires or is revoked; the row stays
 * so the ticket keeps a record of what happened.
 *
 * @property int $id
 * @property string $token
 * @property SecretKind $kind
 * @property int $ticket_id
 * @property int|null $ticket_message_id
 * @property int|null $created_by
 * @property int|null $submitted_by
 * @property string $label
 * @property string|null $ciphertext
 * @property int $max_views
 * @property int $views
 * @property CarbonImmutable $expires_at
 * @property CarbonImmutable|null $submitted_at
 * @property CarbonImmutable|null $revoked_at
 * @property CarbonImmutable|null $destroyed_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Ticket $ticket
 * @property-read TicketMessage|null $message
 * @property-read User|null $creator
 * @property-read User|null $submitter
 */
#[Fillable(['kind', 'ticket_id', 'ticket_message_id', 'created_by', 'label', 'max_views', 'expires_at'])]
#[Hidden(['ciphertext'])]
#[UseFactory(SecretFactory::class)]
#[UsePolicy(SecretPolicy::class)]
class Secret extends Model
{
    /** @use HasFactory<SecretFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (Secret $secret): void {
            $secret->token ??= bin2hex(random_bytes(16));
        });
    }

    /**
     * @return BelongsTo<Ticket, $this>
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    /**
     * The reply the secret was sent with; null until it is sent, or for links copied by hand.
     *
     * @return BelongsTo<TicketMessage, $this>
     */
    public function message(): BelongsTo
    {
        return $this->belongsTo(TicketMessage::class, 'ticket_message_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * The customer who answered a request.
     *
     * @return BelongsTo<User, $this>
     */
    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function status(): SecretStatus
    {
        return match (true) {
            $this->revoked_at !== null => SecretStatus::Revoked,
            $this->views >= $this->max_views => SecretStatus::UsedUp,
            $this->expires_at->isPast() => SecretStatus::Expired,
            $this->ciphertext === null => SecretStatus::Pending,
            default => SecretStatus::Available,
        };
    }

    public function isRequest(): bool
    {
        return $this->kind === SecretKind::Request;
    }

    /**
     * The page the customer opens (after signing in).
     */
    public function url(): string
    {
        return route('secrets.show', $this);
    }

    /**
     * Note a step in the secret's life on the ticket's activity log. Only the label is
     * recorded, never anything about the content.
     *
     * @param  array<string, mixed>  $properties
     */
    public function recordActivity(string $event, ?User $causer = null, array $properties = []): void
    {
        activity()
            ->performedOn($this->ticket)
            ->causedBy($causer)
            ->event($event)
            ->withProperties(['secret' => $this->label, ...$properties])
            ->log($event);
    }

    /**
     * Forget the encrypted content for good.
     */
    public function destroyContent(): void
    {
        $this->forceFill(['ciphertext' => null, 'destroyed_at' => now()])->save();
    }

    /**
     * Secrets whose time is up but whose content hasn't been wiped yet.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function expiredWithContent(Builder $query): void
    {
        $query->whereNull('destroyed_at')->where('expires_at', '<=', now());
    }

    public function getRouteKeyName(): string
    {
        return 'token';
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => SecretKind::class,
            'max_views' => 'integer',
            'views' => 'integer',
            'expires_at' => 'datetime',
            'submitted_at' => 'datetime',
            'revoked_at' => 'datetime',
            'destroyed_at' => 'datetime',
        ];
    }
}
