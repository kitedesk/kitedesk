<?php

namespace App\Http\Requests\Admin;

use App\Domain\Accounts\Enums\Permission;
use App\Domain\Accounts\Enums\UserType;
use App\Domain\Accounts\Models\Role;
use App\Domain\Entitlements\Enums\Limit;
use App\Domain\Entitlements\Support\PlanLimits;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * A person is either a customer or a team member with one role.
 */
class SaveUserRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $user = $this->route('user');

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user instanceof User ? $user->id : null)],
            'type' => ['required', Rule::enum(UserType::class)],
            'role_id' => ['nullable', 'required_if:type,'.UserType::Staff->value, 'integer', Rule::exists('roles', 'id')],
            'organization_id' => ['nullable', 'integer', Rule::exists('organizations', 'id')],
            'timezone' => ['nullable', 'timezone:all'],
            'locale' => ['nullable', Rule::in(array_keys(config('kitedesk.locales', [])))],
            'job_title' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'group_ids' => ['sometimes', 'array'],
            'group_ids.*' => ['integer', Rule::exists('groups', 'id')],
        ];
    }

    /**
     * The chosen role for a team member.
     */
    public function role(): Role
    {
        return Role::query()->findOrFail($this->integer('role_id'));
    }

    /**
     * The role to give: staff only.
     */
    public function chosenRole(): ?Role
    {
        return $this->validated('type') === UserType::Staff->value ? $this->role() : null;
    }

    /**
     * The validated input in the shape `SaveUser` takes.
     *
     * @return array{name: string, email: string, type: string, organization_id?: int|null, timezone?: string|null, locale?: string|null, job_title?: string|null, phone?: string|null, group_ids?: list<int>}
     */
    public function userData(): array
    {
        /** @var array{name: string, email: string, type: string, organization_id?: int|null, timezone?: string|null, locale?: string|null, job_title?: string|null, phone?: string|null, group_ids?: list<int>} */
        return collect($this->validated())->except('role_id')->all();
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $user = $this->route('user');
                // Deactivated people hold no seat until they're reactivated.
                $addsSeat = $this->input('type') === UserType::Staff->value && ! ($user instanceof User && ($user->isStaff() || $user->isDeactivated()));
                $planError = $addsSeat ? PlanLimits::errorFor(Limit::AgentSeats) : null;

                if ($planError !== null) {
                    $validator->errors()->add('role_id', $planError);
                }

                if (! $user instanceof User || $validator->errors()->isNotEmpty()) {
                    return;
                }

                $role = $this->input('type') === UserType::Staff->value ? $this->role() : null;

                if ($user->is($this->user()) && ! $role?->hasPermissionTo(Permission::ManageTeam->value)) {
                    $validator->errors()->add('role_id', __('You cannot remove your own access to user management.'));

                    return;
                }

                if ($user->isAdmin() && ! $user->isDeactivated() && $role?->isAdministrator() !== true && User::administrators()->count() <= 1) {
                    $validator->errors()->add('role_id', __('The help desk needs at least one administrator.'));
                }
            },
        ];
    }
}
