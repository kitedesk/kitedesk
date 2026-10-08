<?php

namespace App\Domain\Mail\Models;

use App\Domain\Accounts\Models\Group;
use App\Domain\Mail\Enums\MailboxDriver;
use App\Domain\Tickets\Models\Ticket;
use App\Domain\Tickets\Models\TicketCategory;
use Carbon\CarbonImmutable;
use Database\Factories\MailboxFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A support address. Mail sent to it becomes tickets; replies on those tickets go out from it.
 *
 * @property int $id
 * @property string $name
 * @property string $address
 * @property bool $is_default
 * @property int|null $default_group_id
 * @property int|null $default_category_id
 * @property MailboxDriver $driver
 * @property string|null $imap_host
 * @property int|null $imap_port
 * @property string|null $imap_encryption
 * @property string|null $imap_username
 * @property string|null $imap_password
 * @property string $imap_folder
 * @property bool $delete_after_import
 * @property string|null $inbound_secret
 * @property bool $is_active
 * @property CarbonImmutable|null $last_polled_at
 * @property string|null $last_error
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Group|null $defaultGroup
 * @property-read TicketCategory|null $defaultCategory
 */
#[Fillable([
    'name', 'address', 'is_default', 'default_group_id', 'default_category_id', 'driver',
    'imap_host', 'imap_port', 'imap_encryption', 'imap_username', 'imap_password', 'imap_folder',
    'delete_after_import', 'inbound_secret', 'is_active',
])]
#[Hidden(['imap_password', 'inbound_secret'])]
#[UseFactory(MailboxFactory::class)]
class Mailbox extends Model
{
    /** @use HasFactory<MailboxFactory> */
    use HasFactory;

    /**
     * The mailbox replies go out from when a ticket didn't arrive through one.
     */
    public static function default(): ?self
    {
        return self::query()->active()->orderByDesc('is_default')->orderBy('id')->first();
    }

    /**
     * The mailbox a ticket's emails are sent from.
     */
    public static function forTicket(Ticket $ticket): ?self
    {
        $mailbox = $ticket->mailbox_id !== null ? self::query()->active()->find($ticket->mailbox_id) : null;

        return $mailbox ?? self::default();
    }

    /**
     * Whether the address belongs to one of our mailboxes (mail from it must never become a ticket).
     */
    public static function owns(string $address): bool
    {
        return self::query()->where('address', mb_strtolower($address))->exists();
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * @return BelongsTo<Group, $this>
     */
    public function defaultGroup(): BelongsTo
    {
        return $this->belongsTo(Group::class, 'default_group_id');
    }

    /**
     * @return BelongsTo<TicketCategory, $this>
     */
    public function defaultCategory(): BelongsTo
    {
        return $this->belongsTo(TicketCategory::class, 'default_category_id');
    }

    /**
     * @return HasMany<Ticket, $this>
     */
    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'driver' => MailboxDriver::class,
            'imap_port' => 'integer',
            'imap_password' => 'encrypted',
            'delete_after_import' => 'boolean',
            'inbound_secret' => 'encrypted',
            'is_active' => 'boolean',
            'last_polled_at' => 'datetime',
        ];
    }
}
