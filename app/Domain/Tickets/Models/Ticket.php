<?php

namespace App\Domain\Tickets\Models;

use App\Domain\Accounts\Enums\TicketAccess;
use App\Domain\Accounts\Models\Group;
use App\Domain\Accounts\Models\Organization;
use App\Domain\Mail\Models\Mailbox;
use App\Domain\Secrets\Models\Secret;
use App\Domain\Sla\Models\SlaPolicy;
use App\Domain\Support\Broadcasting\Channels;
use App\Domain\Tickets\Enums\TicketChannel;
use App\Domain\Tickets\Enums\TicketPriority;
use App\Domain\Tickets\Enums\TicketStatus;
use App\Domain\Tickets\Enums\TicketType;
use App\Domain\Tickets\Policies\TicketPolicy;
use App\Domain\Tickets\Support\CustomStatuses;
use App\Domain\Tickets\Support\TicketViews;
use App\Domain\Workflows\Models\WorkflowRun;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Factories\TicketFactory;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Laravel\Scout\Searchable;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * @property int $id
 * @property string|null $number
 * @property string $subject
 * @property TicketStatus $status
 * @property int|null $ticket_status_id
 * @property TicketPriority $priority
 * @property TicketType|null $type
 * @property TicketChannel $channel
 * @property int $requester_id
 * @property int|null $assignee_id
 * @property int|null $group_id
 * @property int|null $category_id
 * @property int|null $ticket_form_id
 * @property int|null $mailbox_id
 * @property int|null $organization_id
 * @property int|null $sla_policy_id
 * @property array<string, mixed>|null $custom_fields
 * @property CarbonImmutable|null $first_response_due_at
 * @property CarbonImmutable|null $next_reply_due_at
 * @property CarbonImmutable|null $resolution_due_at
 * @property int|null $resolution_remaining_minutes
 * @property CarbonImmutable|null $first_responded_at
 * @property CarbonImmutable|null $sla_breached_at
 * @property CarbonImmutable|null $solved_at
 * @property CarbonImmutable|null $closed_at
 * @property CarbonImmutable|null $last_customer_reply_at
 * @property CarbonImmutable|null $last_agent_reply_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read CustomStatus|null $customStatus
 * @property-read User $requester
 * @property-read User|null $assignee
 * @property-read Group|null $group
 * @property-read TicketCategory|null $category
 * @property-read TicketForm|null $form
 * @property-read Mailbox|null $mailbox
 * @property-read Organization|null $organization
 * @property-read SlaPolicy|null $slaPolicy
 * @property-read Collection<int, TicketMessage> $messages
 * @property-read Collection<int, Secret> $secrets
 * @property-read TicketMessage|null $latestMessage
 * @property-read Collection<int, Tag> $tags
 * @property-read Collection<int, User> $collaborators
 * @property int|null $merged_into_id
 * @property-read Ticket|null $mergedInto
 * @property-read Collection<int, Ticket> $linkedTickets
 * @property-read SatisfactionRating|null $satisfactionRating
 */
#[Fillable([
    'number', 'subject', 'status', 'ticket_status_id', 'priority', 'type', 'channel', 'requester_id', 'assignee_id',
    'group_id', 'category_id', 'ticket_form_id', 'organization_id', 'sla_policy_id', 'custom_fields',
    'first_response_due_at', 'next_reply_due_at', 'resolution_due_at', 'resolution_remaining_minutes',
    'first_responded_at', 'sla_breached_at', 'solved_at', 'closed_at',
])]
#[UseFactory(TicketFactory::class)]
#[UsePolicy(TicketPolicy::class)]
class Ticket extends Model
{
    /** @use HasFactory<TicketFactory> */
    use HasFactory, LogsActivity, Searchable;

    protected static function booted(): void
    {
        static::saving(fn (self $ticket) => CustomStatuses::sync($ticket));
        static::saved(fn () => TicketViews::forgetCounts());
        static::deleted(fn () => TicketViews::forgetCounts());
    }

    /**
     * The admin-defined status; `status` holds its category.
     *
     * @return BelongsTo<CustomStatus, $this>
     */
    public function customStatus(): BelongsTo
    {
        return $this->belongsTo(CustomStatus::class, 'ticket_status_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    /**
     * @return BelongsTo<Group, $this>
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    /**
     * How people refer to the ticket: "#42" for plain numbers, otherwise the number itself ("TKT-00042").
     */
    public function reference(): string
    {
        $number = $this->number ?? (string) $this->id;

        return ctype_digit($number) ? '#'.$number : $number;
    }

    /**
     * Find a ticket from what people type or quote: "#42", "42" or "TKT-00042".
     */
    public static function findByReference(string $reference): ?self
    {
        $reference = trim($reference);
        $number = ltrim($reference, '#');

        if ($number === '') {
            return null;
        }

        // Numbers win; plain digits also match ids, for links and emails from before the format changed.
        return self::query()->where('number', $number)->first()
            ?? (ctype_digit($number) ? self::query()->find((int) $number) : null);
    }

    /**
     * The ticket this one was merged into, if it was a duplicate.
     *
     * @return BelongsTo<Ticket, $this>
     */
    public function mergedInto(): BelongsTo
    {
        return $this->belongsTo(self::class, 'merged_into_id');
    }

    /**
     * Related tickets. Links are stored in both directions, so this lists them from either side.
     *
     * @return BelongsToMany<Ticket, $this>
     */
    public function linkedTickets(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'ticket_links', 'ticket_id', 'linked_ticket_id')->withTimestamps();
    }

    public function linkTo(Ticket $other): void
    {
        if ($other->is($this)) {
            return;
        }

        $this->linkedTickets()->syncWithoutDetaching([$other->id]);
        $other->linkedTickets()->syncWithoutDetaching([$this->id]);
    }

    public function unlinkFrom(Ticket $other): void
    {
        $this->linkedTickets()->detach($other->id);
        $other->linkedTickets()->detach($this->id);
    }

    /**
     * People copied on the ticket (CC): they receive public replies and can respond.
     *
     * @return BelongsToMany<User, $this>
     */
    public function collaborators(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'ticket_collaborators')->withTimestamps();
    }

    /**
     * Ids of the people copied on the ticket.
     *
     * @return list<int>
     */
    public function collaboratorIds(): array
    {
        return array_values(array_map(intval(...), $this->collaborators()->pluck('users.id')->all()));
    }

    /**
     * Whether the user is the requester or copied on the ticket.
     */
    public function involves(User $user): bool
    {
        return $this->requester_id === $user->id
            || $this->collaborators()->whereKey($user->id)->exists();
    }

    /**
     * Where customers hear about this ticket: its own channel (open ticket pages) and the
     * personal channel of the requester and everyone copied (their request lists).
     *
     * @param  list<int>  $alsoNotify  Extra people, e.g. collaborators who were just removed.
     * @return list<PrivateChannel>
     */
    public function customerChannels(array $alsoNotify = []): array
    {
        $userIds = array_unique([$this->requester_id, ...$this->collaboratorIds(), ...$alsoNotify]);

        return [
            new PrivateChannel(Channels::name('tickets.'.$this->id)),
            ...array_map(fn (int $id): PrivateChannel => new PrivateChannel(Channels::name('App.Models.User.'.$id)), array_values($userIds)),
        ];
    }

    /**
     * @return BelongsTo<TicketCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(TicketCategory::class, 'category_id');
    }

    /**
     * @return BelongsTo<TicketForm, $this>
     */
    public function form(): BelongsTo
    {
        return $this->belongsTo(TicketForm::class, 'ticket_form_id');
    }

    /**
     * The support address the ticket arrived at; its emails go out from there.
     *
     * @return BelongsTo<Mailbox, $this>
     */
    public function mailbox(): BelongsTo
    {
        return $this->belongsTo(Mailbox::class);
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * @return BelongsTo<SlaPolicy, $this>
     */
    public function slaPolicy(): BelongsTo
    {
        return $this->belongsTo(SlaPolicy::class);
    }

    /**
     * @return HasMany<TicketMessage, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(TicketMessage::class);
    }

    /**
     * @return HasMany<Secret, $this>
     */
    public function secrets(): HasMany
    {
        return $this->hasMany(Secret::class);
    }

    /**
     * @return HasOne<TicketMessage, $this>
     */
    public function latestMessage(): HasOne
    {
        return $this->hasOne(TicketMessage::class)->latestOfMany();
    }

    /**
     * The customer's rating of how the ticket was handled, once the survey went out or they rated it.
     *
     * @return HasOne<SatisfactionRating, $this>
     */
    public function satisfactionRating(): HasOne
    {
        return $this->hasOne(SatisfactionRating::class);
    }

    /**
     * Workflow executions for this ticket.
     *
     * @return HasMany<WorkflowRun, $this>
     */
    public function workflowRuns(): HasMany
    {
        return $this->hasMany(WorkflowRun::class);
    }

    /**
     * @return BelongsToMany<Tag, $this>
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }

    /**
     * Limit the query to tickets that still need work.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function unresolved(Builder $query): void
    {
        $query->whereIn('status', TicketStatus::unresolved());
    }

    /**
     * Limit the query to tickets the given user is allowed to see.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function visibleTo(Builder $query, User $user): void
    {
        $access = $user->isStaff() ? $user->ticketAccess() : null;

        if ($access === TicketAccess::All) {
            return;
        }

        $query->where(fn (Builder $visible) => $visible
            ->where('requester_id', $user->id)
            ->orWhereHas('collaborators', fn (Builder $collaborators) => $collaborators->whereKey($user->id))
            ->when($access !== null, fn (Builder $staff) => $staff->orWhere('assignee_id', $user->id))
            ->when($access === TicketAccess::Groups, fn (Builder $groups) => $groups->orWhereIn('group_id', $user->groups()->select('groups.id'))));
    }

    /**
     * Whether the user may see this ticket: the in-memory counterpart of `visibleTo`.
     */
    public function isVisibleTo(User $user): bool
    {
        $access = $user->isStaff() ? $user->ticketAccess() : null;

        return match (true) {
            $access === TicketAccess::All => true,
            $access !== null && $this->assignee_id === $user->id => true,
            $access === TicketAccess::Groups && $this->group_id !== null && $user->groups()->whereKey($this->group_id)->exists() => true,
            default => $this->involves($user),
        };
    }

    /**
     * Whether any SLA target is currently past due.
     */
    public function isBreachingSla(): bool
    {
        return collect([$this->first_response_due_at, $this->next_reply_due_at, $this->resolution_due_at])
            ->filter()
            ->contains(fn (CarbonImmutable $due): bool => $due->isPast());
    }

    /**
     * Get the indexable data array for the model.
     *
     * @return array<string, mixed>
     */
    public function toSearchableArray(): array
    {
        return [
            'id' => $this->id,
            'subject' => $this->subject,
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['subject', 'status', 'ticket_status_id', 'priority', 'type', 'assignee_id', 'group_id', 'category_id'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => TicketStatus::class,
            'priority' => TicketPriority::class,
            'type' => TicketType::class,
            'channel' => TicketChannel::class,
            'custom_fields' => 'array',
            'first_response_due_at' => 'datetime',
            'next_reply_due_at' => 'datetime',
            'resolution_due_at' => 'datetime',
            'first_responded_at' => 'datetime',
            'sla_breached_at' => 'datetime',
            'solved_at' => 'datetime',
            'closed_at' => 'datetime',
            'last_customer_reply_at' => 'datetime',
            'last_agent_reply_at' => 'datetime',
        ];
    }
}
