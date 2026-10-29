<?php

namespace Tests\Fixtures\KiteDeskDemo;

use App\Domain\Ai\Mcp\McpAccess;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('demo_tool')]
#[Description('A tool a package adds to the MCP server.')]
class DemoTool extends Tool
{
    public function shouldRegister(Request $request): bool
    {
        return McpAccess::allows($request->user(), 'demo:read');
    }

    public function handle(Request $request): Response
    {
        return Response::text('demo');
    }
}
