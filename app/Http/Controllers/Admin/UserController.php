<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Accounts\Actions\DeactivateUser;
use App\Domain\Accounts\Actions\ReactivateUser;
use App\Domain\Accounts\Actions\SaveUser;
use App\Domain\Accounts\Enums\UserType;
use App\Domain\Accounts\Models\Group;
use App\Domain\Accounts\Models\Organization;
use App\Domain\Accounts\Models\Role;
use App\Domain\Accounts\Support\RoleCatalog;
use App\Domain\Accounts\Support\UserSessions;
use App\Domain\Tickets\Enums\TicketStatus;
use App\Domain\Tickets\Models\Ticket;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveUserRequest;
use App\Http\Resources\TicketResource;
use App\Models\User;
use DateTimeZone;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Passport\Token;
use Spatie\Activitylog\Models\Activity;

class UserController extends Controller
{
    public function index(Request $request): Response
    {
        $audience = in_array($request->string('audience')->toString(), ['customers', 'deactivated'], true) ? $request->string('audience')->toString() : 'staff';
        $search = trim($request->string('search')->toString());

        $users = User::query()
            ->when($audience === 'deactivated', fn (Builder $query) => $query->whereNotNull('deactivated_at'), fn (Builder $query) => $query->active())
            ->when($audience === 'staff', fn (Builder $query) => $query->staff())
            ->when($audience === 'customers', fn (Builder $query) => $query->customers())
            ->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $inner) => $inner
                ->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")))
            ->with(['organization:id,name', 'groups:id,name', 'roles'])
            ->withCount('requestedTickets')
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'avatar' => $user->avatar,
                'type' => $user->type->value,
                'role_label' => $user->staffRole()?->displayName() ?? $user->type->label(),
                'organization' => $user->organization?->only(['id', 'name']),
                'groups' => $user->groups->map->only(['id', 'name'])->values(),
                'tickets_count' => $user->requested_tickets_count,
                'email_verified' => $user->email_verified_at !== null,
                'invited' => $user->last_login_at === null,
                'deactivated' => $user->isDeactivated(),
                'last_login_at' => $user->last_login_at?->toIso8601String(),
                'created_at' => $user->created_at?->toIso8601String(),
            ]);

        return Inertia::render('admin/users/index', [
            'audience' => $audience,
            'search' => $search,
            'users' => $users,
            'deactivatedCount' => User::query()->whereNotNull('deactivated_at')->count(),
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('admin/users/form', [
            'user' => null,
            'defaultRole' => $request->string('audience')->toString() === 'customers'
                ? UserType::Customer->value
                : (string) Role::query()->where('name', RoleCatalog::AGENT)->where('is_system', true)->value('id'),
            ...$this->formOptions(),
        ]);
    }

    public function store(SaveUserRequest $request, SaveUser $saveUser): RedirectResponse
    {
        $user = $saveUser->create($request->userData(), $request->chosenRole(), $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Invitation sent to :email.', ['email' => $user->email])]);

        return to_route('admin.users.show', $user);
    }

    public function show(Request $request, User $user): Response
    {
        $user->load(['organization:id,name', 'groups:id,name', 'roles']);

        return Inertia::render('admin/users/show', [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'avatar' => $user->avatar,
                'type' => $user->type->value,
                'role_label' => $user->staffRole()?->displayName() ?? $user->type->label(),
                'is_admin' => $user->isAdmin(),
                'organization' => $user->organization?->only(['id', 'name']),
                'groups' => $user->groups->map->only(['id', 'name'])->values(),
                'job_title' => $user->job_title,
                'phone' => $user->phone,
                'timezone' => $user->timezone,
                'locale' => $user->preferredLocale() !== null ? config('kitedesk.locales.'.$user->preferredLocale()) : null,
                'is_available' => $user->is_available,
                'email_verified' => $user->email_verified_at !== null,
                'invited' => $user->last_login_at === null,
                'last_login_at' => $user->last_login_at?->toIso8601String(),
                'deactivated_at' => $user->deactivated_at?->toIso8601String(),
                'created_at' => $user->created_at?->toIso8601String(),
                'is_self' => $user->is($request->user()),
            ],
            'stats' => $this->statsFor($user),
            'tickets' => Inertia::defer(fn () => TicketResource::collection(
                Ticket::query()
                    ->where($user->isStaff() ? 'assignee_id' : 'requester_id', $user->id)
                    ->visibleTo($request->user())
                    ->latest('updated_at')
                    ->limit(8)
                    ->get(),
            )->resolve($request)),
            'security' => Inertia::defer(fn () => [
                'two_factor_enabled' => $user->hasEnabledTwoFactorAuthentication(),
                'passkeys' => $user->passkeys()->count(),
                'api_tokens' => $user->tokens()->count(),
                'connected_apps' => Token::query()->where('user_id', $user->id)->where('revoked', false)->where('expires_at', '>', now())->distinct()->count('client_id'),
                'sessions' => UserSessions::for($user, $user->is($request->user()) ? $request->session()->getId() : null),
            ]),
            'activity' => Inertia::defer(fn () => $this->activityFor($user)),
        ]);
    }

    public function edit(User $user): Response
    {
        $user->load(['groups:id', 'roles']);

        return Inertia::render('admin/users/form', [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->staffRole() !== null ? (string) $user->staffRole()->id : UserType::Customer->value,
                'avatar' => $user->avatar,
                'organization_id' => $user->organization_id,
                'timezone' => $user->timezone,
                'locale' => $user->locale,
                'job_title' => $user->job_title,
                'phone' => $user->phone,
                'group_ids' => $user->groups->pluck('id'),
            ],
            'defaultRole' => UserType::Customer->value,
            ...$this->formOptions(),
        ]);
    }

    public function update(SaveUserRequest $request, User $user, SaveUser $saveUser): RedirectResponse
    {
        // The form always sends the full group list; an empty one is left out of the request.
        $saveUser->update($user, ['group_ids' => [], ...$request->userData()], $request->chosenRole(), $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Saved.')]);

        return to_route('admin.users.show', $user);
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        abort_if($user->is($request->user()), 422, __('You cannot delete your own account here.'));
        abort_if($user->isAdmin() && ! $user->isDeactivated() && User::administrators()->count() <= 1, 422, __('The help desk needs at least one administrator.'));

        $user->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('User deleted.')]);

        return to_route('admin.users.index', ['audience' => $user->isStaff() ? 'staff' : 'customers']);
    }

    public function resendInvitation(User $user): RedirectResponse
    {
        abort_if($user->isDeactivated(), 422, __('This account has been deactivated.'));

        Password::broker()->sendResetLink(['email' => $user->email]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Invitation sent to :email.', ['email' => $user->email])]);

        return back();
    }

    /**
     * @throws ValidationException
     */
    public function deactivate(Request $request, User $user, DeactivateUser $deactivate): RedirectResponse
    {
        $deactivate->handle($user, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':name was deactivated.', ['name' => $user->name])]);

        return back();
    }

    /**
     * @throws ValidationException
     */
    public function reactivate(Request $request, User $user, ReactivateUser $reactivate): RedirectResponse
    {
        $reactivate->handle($user, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':name was reactivated.', ['name' => $user->name])]);

        return back();
    }

    /**
     * Sign the person out of every browser.
     */
    public function destroySessions(Request $request, User $user): RedirectResponse
    {
        abort_if($user->is($request->user()), 422, __('Use your security settings to sign out of your own sessions.'));

        $user->forceFill(['remember_token' => Str::random(60)])->save();
        UserSessions::forget($user);

        activity()->performedOn($user)->causedBy($request->user())->event('sessions_revoked')->log('sessions_revoked');

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':name was signed out everywhere.', ['name' => $user->name])]);

        return back();
    }

    /**
     * Turn off two-factor authentication and remove passkeys, for someone who lost their device.
     */
    public function resetTwoFactor(Request $request, User $user): RedirectResponse
    {
        abort_if($user->is($request->user()), 422, __('Use your security settings to change your own two-factor authentication.'));

        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();
        $user->passkeys()->delete();

        activity()->performedOn($user)->causedBy($request->user())->event('two_factor_reset')->log('two_factor_reset');

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Two-factor authentication and passkeys were reset for :name.', ['name' => $user->name])]);

        return back();
    }

    /**
     * Ticket counts for the overview: what they asked for, and for team members what they work on.
     *
     * @return array<string, int>
     */
    private function statsFor(User $user): array
    {
        $requested = Ticket::query()->where('requester_id', $user->id);

        $stats = [
            'requested' => (clone $requested)->count(),
            'requested_open' => (clone $requested)->unresolved()->count(),
        ];

        if ($user->isStaff()) {
            $assigned = Ticket::query()->where('assignee_id', $user->id);

            $stats['assigned_open'] = (clone $assigned)->unresolved()->count();
            $stats['solved_recently'] = (clone $assigned)
                ->whereIn('status', [TicketStatus::Solved, TicketStatus::Closed])
                ->where('solved_at', '>=', now()->subDays(30))
                ->count();
        } else {
            $stats['requested_solved'] = $stats['requested'] - $stats['requested_open'];
        }

        return $stats;
    }

    /**
     * Changes made to this person and things they did, newest first.
     *
     * @return list<array<string, mixed>>
     */
    private function activityFor(User $user): array
    {
        return array_values(Activity::query()
            ->where(fn (Builder $query) => $query
                ->where(fn (Builder $subject) => $subject->where('subject_type', $user->getMorphClass())->where('subject_id', $user->id))
                ->orWhere(fn (Builder $causer) => $causer->where('causer_type', $user->getMorphClass())->where('causer_id', $user->id)))
            ->with(['causer', 'subject'])
            ->latest()
            ->latest('id')
            ->limit(50)
            ->get()
            ->map(fn (Activity $activity): array => [
                'id' => $activity->id,
                'event' => $activity->event,
                'description' => $activity->description,
                'causer' => $activity->causer instanceof User ? $activity->causer->name : $activity->properties?->get('operator'),
                'by_this_user' => $activity->causer instanceof User && $activity->causer->is($user),
                'subject' => match (true) {
                    $activity->subject instanceof Ticket => ['type' => 'ticket', 'id' => $activity->subject->id, 'label' => $activity->subject->reference().' · '.$activity->subject->subject],
                    $activity->subject instanceof User && ! $activity->subject->is($user) => ['type' => 'user', 'id' => $activity->subject->id, 'label' => $activity->subject->name],
                    default => null,
                },
                'changes' => [
                    'old' => $activity->attribute_changes?->get('old') ?? [],
                    'new' => $activity->attribute_changes?->get('attributes') ?? [],
                ],
                'created_at' => $activity->created_at?->toIso8601String(),
            ])
            ->all());
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        return [
            'timezones' => DateTimeZone::listIdentifiers(),
            'locales' => config('kitedesk.locales'),
            'roles' => Role::query()
                ->orderByDesc('is_system')
                ->orderBy('id')
                ->get()
                ->map(fn (Role $role): array => [
                    'value' => (string) $role->id,
                    'label' => $role->displayName(),
                    'description' => $role->displayDescription(),
                ]),
            'groups' => Group::query()->orderBy('name')->get(['id', 'name']),
            'organizations' => Organization::query()->orderBy('name')->get(['id', 'name']),
        ];
    }
}
