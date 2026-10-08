<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Accounts\Models\Group;
use App\Http\Requests\Admin\SaveGroupRequest;
use App\Http\Resources\Api\V1\GroupResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * List groups with `GET /groups` (ticket setup).
 *
 * @tags Groups
 */
class GroupController extends ApiController
{
    /**
     * Create a group.
     *
     * `agent_ids` are the staff members who work its tickets.
     */
    public function store(SaveGroupRequest $request): JsonResponse
    {
        $group = Group::query()->create($request->safe()->only(['name', 'description', 'assignment_mode']));
        $group->agents()->sync($request->validated('agent_ids', []));

        return (new GroupResource($group->load('agents:id')))->response()->setStatusCode(201);
    }

    /**
     * Update a group.
     *
     * Send the full group, as when creating. `agent_ids` is only changed when given.
     */
    public function update(SaveGroupRequest $request, Group $group): GroupResource
    {
        $group->update($request->safe()->only(['name', 'description', 'assignment_mode']));

        if ($request->has('agent_ids')) {
            $group->agents()->sync($request->validated('agent_ids', []));
        }

        return new GroupResource($group->load('agents:id'));
    }

    /**
     * Delete a group.
     *
     * Its tickets stay, without a group.
     */
    public function destroy(Group $group): Response
    {
        $group->delete();

        return response()->noContent();
    }
}
