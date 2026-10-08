<?php

namespace App\Domain\Accounts\Actions;

use App\Domain\Accounts\Models\Role;
use App\Domain\Webhooks\Enums\WebhookEvent;
use App\Domain\Webhooks\Support\Webhooks;
use App\Http\Resources\Api\V1\UserResource;
use App\Models\User;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

/**
 * Creates or updates a person from the admin center or the API, with input validated by
 * `SaveUserRequest`: their type, role (staff), organization and groups (staff). New people get
 * an emailed link to choose a password unless `$invite` is false; they can still use "Forgot
 * password" later. Changes are recorded in the activity log and sent to webhooks.
 */
class SaveUser
{
    /**
     * @param  array{name: string, email: string, type: string, organization_id?: int|null, timezone?: string|null, locale?: string|null, job_title?: string|null, phone?: string|null, group_ids?: list<int>}  $data
     */
    public function create(array $data, ?Role $role, User $actor, bool $invite = true): User
    {
        $user = new User([
            ...$this->profile($data),
            'password' => Str::random(40),
        ]);
        $user->forceFill([
            'type' => $data['type'],
            'organization_id' => $data['organization_id'] ?? null,
            // The admin vouches for the address; the invitation link proves ownership.
            'email_verified_at' => now(),
        ])->save();

        $user->syncRoles($user->isStaff() && $role !== null ? [$role] : []);
        $user->groups()->sync($user->isStaff() ? $data['group_ids'] ?? [] : []);

        if ($invite) {
            Password::broker()->sendResetLink(['email' => $user->email]);
        }

        activity()->performedOn($user)->causedBy($actor)->event('created')->log('created');
        Webhooks::send(WebhookEvent::UserCreated, fn (): array => ['user' => (new UserResource($user))->resolve()]);

        return $user;
    }

    /**
     * Groups are only changed when `group_ids` is given.
     *
     * @param  array{name: string, email: string, type: string, organization_id?: int|null, timezone?: string|null, locale?: string|null, job_title?: string|null, phone?: string|null, group_ids?: list<int>}  $data
     */
    public function update(User $user, array $data, ?Role $role, User $actor): User
    {
        $user->load(['organization:id,name', 'groups:id,name', 'roles']);
        $before = $this->auditedFields($user);

        $user->fill($this->profile($data))->forceFill([
            'type' => $data['type'],
            'organization_id' => $data['organization_id'] ?? null,
        ])->save();

        $user->syncRoles($user->isStaff() && $role !== null ? [$role] : []);

        if (! $user->isStaff()) {
            $user->groups()->sync([]);
        } elseif (array_key_exists('group_ids', $data)) {
            $user->groups()->sync($data['group_ids']);
        }

        $after = $this->auditedFields($user->unsetRelation('roles')->unsetRelation('groups')->load(['organization:id,name', 'groups:id,name', 'roles']));
        $changed = array_keys(array_filter($after, fn (mixed $value, string $field): bool => $value !== $before[$field], ARRAY_FILTER_USE_BOTH));

        if ($changed !== []) {
            activity()->performedOn($user)->causedBy($actor)->event('updated')
                ->withChanges(['old' => array_intersect_key($before, array_flip($changed)), 'attributes' => array_intersect_key($after, array_flip($changed))])
                ->log('updated');

            Webhooks::send(WebhookEvent::UserUpdated, fn (): array => ['user' => (new UserResource($user))->resolve()], array_map(
                fn (string $field): array => ['from' => $before[$field], 'to' => $after[$field]],
                array_combine($changed, $changed),
            ));
        }

        return $user;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function profile(array $data): array
    {
        return [
            'name' => $data['name'],
            'email' => Str::lower($data['email']),
            ...array_intersect_key($data, array_flip(['timezone', 'locale', 'job_title', 'phone'])),
        ];
    }

    /**
     * @return array<string, string|null>
     */
    private function auditedFields(User $user): array
    {
        return [
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->staffRole()?->displayName() ?? $user->type->label(),
            'organization' => $user->organization?->name,
            'groups' => $user->groups->pluck('name')->sort()->implode(', ') ?: null,
            'job_title' => $user->job_title,
            'phone' => $user->phone,
            'timezone' => $user->timezone,
            'locale' => $user->locale,
        ];
    }
}
