<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Accounts\Models\Role;
use Illuminate\Http\JsonResponse;

/**
 * @tags Users
 */
class RoleController extends ApiController
{
    /**
     * List roles.
     *
     * Use a role's `id` as `role_id` when creating staff. Roles are managed in the admin center.
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => Role::query()->with('permissions')->orderBy('name')->get()->map(fn (Role $role): array => [
                'id' => $role->id,
                'name' => $role->displayName(),
                'ticket_access' => $role->ticket_access->value,
                /** @var list<string> */
                'permissions' => $role->permissions->pluck('name')->sort()->values()->all(),
            ])->values()->all(),
        ]);
    }
}
