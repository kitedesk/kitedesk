<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Accounts\Enums\AssignmentMode;
use App\Domain\Accounts\Models\Group;
use App\Domain\Support\EnumOptions;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveGroupRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class GroupController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/groups/index', [
            'groups' => Group::query()
                ->with('agents:id,name')
                ->withCount(['tickets as open_tickets_count' => fn ($query) => $query->unresolved()])
                ->orderBy('name')
                ->get()
                ->map(fn (Group $group): array => [
                    'id' => $group->id,
                    'name' => $group->name,
                    'description' => $group->description,
                    'assignment_mode' => $group->assignment_mode->value,
                    'agents' => $group->agents->map->only(['id', 'name'])->values(),
                    'open_tickets_count' => $group->getAttribute('open_tickets_count'),
                ]),
            'staff' => User::query()->staff()->orderBy('name')->get(['id', 'name']),
            'assignmentModes' => EnumOptions::for(AssignmentMode::class),
        ]);
    }

    public function store(SaveGroupRequest $request): RedirectResponse
    {
        $group = Group::query()->create($request->safe()->only(['name', 'description', 'assignment_mode']));
        $group->agents()->sync($request->validated('agent_ids', []));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Group created.')]);

        return back();
    }

    public function update(SaveGroupRequest $request, Group $group): RedirectResponse
    {
        $group->update($request->safe()->only(['name', 'description', 'assignment_mode']));
        $group->agents()->sync($request->validated('agent_ids', []));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Group saved.')]);

        return back();
    }

    public function destroy(Group $group): RedirectResponse
    {
        $group->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Group deleted. Its tickets are now ungrouped.')]);

        return back();
    }
}
