<?php

namespace App\Domain\Ai\Mcp\Tools;

use App\Domain\Accounts\Models\Group;
use App\Domain\Ai\Mcp\McpAccess;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('list_team')]
#[Description('List the groups and the agents who can be assigned tickets, with the ids update_ticket expects.')]
#[IsReadOnly]
class ListTeamTool extends Tool
{
    public function shouldRegister(Request $request): bool
    {
        return McpAccess::allows($request->user(), 'tickets:write');
    }

    public function handle(Request $request): Response
    {
        return Response::json([
            'groups' => Group::query()->orderBy('name')->get(['id', 'name'])
                ->map(fn (Group $group): array => ['id' => $group->id, 'name' => $group->name])->all(),
            'agents' => User::query()->assignable()->orderBy('name')->get(['id', 'name', 'email'])
                ->map(fn (User $agent): array => ['id' => $agent->id, 'name' => $agent->name, 'email' => $agent->email])->all(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
