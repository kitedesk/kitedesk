<?php

namespace App\Http\Requests\Admin;

use App\Domain\Accounts\Enums\Permission;
use App\Domain\Accounts\Enums\TicketAccess;
use App\Domain\Accounts\Models\Role;
use App\Domain\Accounts\Support\PermissionRegistry;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveRoleRequest extends FormRequest
{
    /**
     * The administrator role is fixed: it always holds every permission.
     */
    public function authorize(): bool
    {
        $role = $this->route('role');

        return ! $role instanceof Role || ! $role->isAdministrator();
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $role = $this->route('role');

        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('roles', 'name')->where('guard_name', 'web')->ignore($role instanceof Role ? $role->id : null)],
            'description' => ['nullable', 'string', 'max:255'],
            'ticket_access' => ['required', Rule::enum(TicketAccess::class)],
            'permissions' => ['present', 'array'],
            'permissions.*' => ['string', 'distinct', Rule::in(app(PermissionRegistry::class)->names())],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $role = $this->route('role');

                if ($role instanceof Role
                    && $this->user()->hasRole($role)
                    && ! in_array(Permission::ManageTeam->value, (array) $this->input('permissions'), true)) {
                    $validator->errors()->add('permissions', __('You cannot remove your own access to user management.'));
                }
            },
        ];
    }

    /**
     * @return array{name: string, description: string|null, ticket_access: string}
     */
    public function roleAttributes(): array
    {
        return [
            'name' => $this->string('name')->trim()->toString(),
            'description' => $this->filled('description') ? $this->string('description')->trim()->toString() : null,
            'ticket_access' => $this->string('ticket_access')->toString(),
        ];
    }

    /**
     * @return list<string>
     */
    public function permissionNames(): array
    {
        return array_values(array_unique((array) $this->validated('permissions')));
    }
}
