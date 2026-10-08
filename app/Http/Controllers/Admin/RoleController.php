<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Accounts\Enums\TicketAccess;
use App\Domain\Accounts\Models\Role;
use App\Domain\Accounts\Support\PermissionDefinition;
use App\Domain\Accounts\Support\PermissionRegistry;
use App\Domain\Entitlements\Enums\Feature;
use App\Domain\Entitlements\Support\PlanLimits;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveRoleRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Staff roles: what each kind of team member may do and which tickets they see.
 */
class RoleController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/roles/index', [
            'roles' => Role::query()
                ->withCount(['users', 'permissions'])
                ->orderByDesc('is_system')
                ->orderBy('id')
                ->get()
                ->map(fn (Role $role): array => [
                    'id' => $role->id,
                    'name' => $role->displayName(),
                    'description' => $role->displayDescription(),
                    'ticket_access' => $role->ticket_access->label(),
                    'is_system' => $role->is_system,
                    'is_locked' => $role->isAdministrator(),
                    'users_count' => $role->users_count,
                    'permissions_count' => $role->permissions_count,
                ]),
            'permissionsTotal' => count(app(PermissionRegistry::class)->all()),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/roles/form', [
            'role' => null,
            ...$this->formOptions(),
        ]);
    }

    public function store(SaveRoleRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request): void {
            $role = Role::create(['name' => $request->roleAttributes()['name']]);
            $role->forceFill($request->roleAttributes())->save();
            $role->syncPermissions($request->permissionNames());
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Role created.')]);

        return to_route('admin.roles.index');
    }

    public function edit(Role $role): Response
    {
        $this->ensureEditable($role);

        return Inertia::render('admin/roles/form', [
            'role' => [
                'id' => $role->id,
                'name' => $role->is_system ? $role->displayName() : $role->name,
                'description' => $role->displayDescription(),
                'ticket_access' => $role->ticket_access->value,
                'permissions' => $role->permissions->pluck('name')->values(),
                'is_system' => $role->is_system,
                'is_locked' => $role->isAdministrator(),
            ],
            ...$this->formOptions(),
        ]);
    }

    public function update(SaveRoleRequest $request, Role $role): RedirectResponse
    {
        $this->ensureEditable($role);

        DB::transaction(function () use ($request, $role): void {
            // Built-in roles keep their stored (English) name and description so they stay translated.
            $attributes = $role->is_system
                ? ['ticket_access' => $request->roleAttributes()['ticket_access']]
                : $request->roleAttributes();

            $role->forceFill($attributes)->save();
            $role->syncPermissions($request->permissionNames());
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Role saved.')]);

        return to_route('admin.roles.index');
    }

    public function destroy(Role $role): RedirectResponse
    {
        if ($role->is_system) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('Built-in roles cannot be deleted.')]);

            return back();
        }

        if ($role->users()->exists()) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('Move its team members to another role before deleting it.')]);

            return back();
        }

        $role->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Role deleted.')]);

        return to_route('admin.roles.index');
    }

    /**
     * Custom roles need the plan; once it's gone they keep working and can be deleted, but not changed.
     */
    private function ensureEditable(Role $role): void
    {
        abort_unless($role->is_system || PlanLimits::allows(Feature::CustomRoles), 403, __('Your plan does not include this feature.'));
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        return [
            'permissionGroups' => collect(app(PermissionRegistry::class)->all())
                ->groupBy(fn (PermissionDefinition $permission): string => $permission->group)
                ->map(fn ($permissions, string $group): array => [
                    'title' => $group,
                    'permissions' => $permissions->map(fn (PermissionDefinition $permission): array => [
                        'value' => $permission->name,
                        'label' => $permission->label,
                        'description' => $permission->description,
                    ])->values()->all(),
                ])
                ->values(),
            'ticketAccessOptions' => collect(TicketAccess::cases())->map(fn (TicketAccess $access): array => [
                'value' => $access->value,
                'label' => $access->label(),
                'description' => $access->description(),
            ]),
        ];
    }
}
