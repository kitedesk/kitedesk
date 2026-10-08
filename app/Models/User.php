<?php

namespace App\Models;

use App\Domain\Accounts\Enums\Permission;
use App\Domain\Accounts\Enums\TicketAccess;
use App\Domain\Accounts\Enums\UserType;
use App\Domain\Accounts\Models\Group;
use App\Domain\Accounts\Models\Organization;
use App\Domain\Accounts\Models\Role;
use App\Domain\Accounts\Support\PermissionDefinition;
use App\Domain\Accounts\Support\PermissionRegistry;
use App\Domain\Accounts\Support\RoleCatalog;
use App\Domain\Accounts\Support\UserAvatars;
use App\Domain\Tickets\Models\SavedView;
use App\Domain\Tickets\Models\Ticket;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\Attributes\Appends;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

/**
 * Type, role and organization are intentionally not mass assignable: they are set
 * explicitly by administrators so that self-registration can never escalate. Staff get
 * one role (spatie/laravel-permission) that decides what they may do.
 *
 * @property int $id
 * @property string $name
 * @property string $email
 * @property UserType $type
 * @property int|null $organization_id
 * @property string|null $timezone
 * @property string|null $locale
 * @property string|null $avatar_path
 * @property string|null $job_title
 * @property string|null $phone
 * @property string|null $signature
 * @property Carbon|null $last_login_at
 * @property Carbon|null $deactivated_at
 * @property-read string|null $avatar
 * @property bool $is_available
 * @property array<string, mixed>|null $preferences
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Organization|null $organization
 * @property-read Collection<int, Group> $groups
 * @property-read Collection<int, Ticket> $requestedTickets
 * @property-read Collection<int, Ticket> $assignedTickets
 * @property-read Collection<int, Ticket> $collaboratingTickets
 * @property-read Collection<int, SavedView> $savedViews
 * @property-read Collection<int, Role> $roles
 */
#[Fillable(['name', 'email', 'password', 'timezone', 'locale', 'job_title', 'phone', 'signature'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token', 'preferences', 'avatar_path'])]
#[Appends(['avatar'])]
class User extends Authenticatable implements HasLocalePreference, MustVerifyEmail, PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'type' => 'customer',
        'is_available' => true,
    ];

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * @return BelongsToMany<Group, $this>
     */
    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(Group::class);
    }

    /**
     * @return HasMany<Ticket, $this>
     */
    public function requestedTickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'requester_id');
    }

    /**
     * @return HasMany<Ticket, $this>
     */
    public function assignedTickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'assignee_id');
    }

    /**
     * Tickets this user is copied on.
     *
     * @return BelongsToMany<Ticket, $this>
     */
    public function collaboratingTickets(): BelongsToMany
    {
        return $this->belongsToMany(Ticket::class, 'ticket_collaborators')->withTimestamps();
    }

    /**
     * Ticket views this user saved (personal, or shared when the user is an admin).
     *
     * @return HasMany<SavedView, $this>
     */
    public function savedViews(): HasMany
    {
        return $this->hasMany(SavedView::class);
    }

    /**
     * Limit the query to support staff.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function staff(Builder $query): void
    {
        $query->where('type', UserType::Staff);
    }

    /**
     * Limit the query to customers (end users).
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function customers(Builder $query): void
    {
        $query->where('type', UserType::Customer);
    }

    /**
     * Limit the query to people who haven't been deactivated.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->whereNull('deactivated_at');
    }

    /**
     * Limit the query to active staff who can be assigned tickets.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function assignable(Builder $query): void
    {
        $query->where('type', UserType::Staff)->whereNull('deactivated_at')->permission(Permission::ReceiveAssignments->value);
    }

    /**
     * Deactivated people can't sign in, use the API or write in; their history stays.
     */
    public function isDeactivated(): bool
    {
        return $this->deactivated_at !== null;
    }

    /**
     * Deactivated people get no email: no ticket updates, password reset or access links.
     */
    public function routeNotificationForMail(): ?string
    {
        return $this->isDeactivated() ? null : $this->email;
    }

    /**
     * The language chosen in the profile, used for the interface and the emails they get.
     */
    public function preferredLocale(): ?string
    {
        return $this->locale !== null && array_key_exists($this->locale, config('kitedesk.locales', [])) ? $this->locale : null;
    }

    /**
     * The URL of the uploaded profile photo, if any.
     *
     * @return Attribute<string|null, never>
     */
    protected function avatar(): Attribute
    {
        return Attribute::get(fn (): ?string => UserAvatars::url($this->avatar_path));
    }

    public function isStaff(): bool
    {
        return $this->type === UserType::Staff;
    }

    public function isCustomer(): bool
    {
        return $this->type === UserType::Customer;
    }

    /**
     * Whether the user holds the built-in administrator role.
     */
    public function isAdmin(): bool
    {
        return $this->isStaff() && $this->roles->contains(fn (Role $role): bool => $role->isAdministrator());
    }

    /**
     * Whether a staff member's role grants the permission. Customers hold none.
     */
    public function hasPermission(Permission|string $permission): bool
    {
        return $this->isStaff() && $this->hasPermissionTo($permission instanceof Permission ? $permission->value : $permission);
    }

    /**
     * Whether the user may open the admin center (any admin section).
     */
    public function canAccessAdmin(): bool
    {
        return collect(app(PermissionRegistry::class)->all())
            ->contains(fn (PermissionDefinition $permission): bool => $permission->isAdminSection() && $this->hasPermission($permission->name));
    }

    public function staffRole(): ?Role
    {
        return $this->isStaff() ? $this->roles->first() : null;
    }

    /**
     * Which tickets the user can see in the agent workspace. Staff without a role only see
     * their own.
     */
    public function ticketAccess(): TicketAccess
    {
        return $this->staffRole()->ticket_access ?? TicketAccess::Assigned;
    }

    /**
     * Give a staff member exactly one role.
     */
    public function assignStaffRole(Role|string $role): static
    {
        $this->syncRoles([$role]);
        $this->unsetRelation('roles');

        return $this;
    }

    /**
     * The active users holding the built-in administrator role.
     *
     * @return Collection<int, self>
     */
    public static function administrators(): Collection
    {
        return self::query()->staff()->active()->role(RoleCatalog::ADMINISTRATOR)->get();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => UserType::class,
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'last_login_at' => 'datetime',
            'deactivated_at' => 'datetime',
            'is_available' => 'boolean',
            'preferences' => 'array',
        ];
    }
}
