<?php

use App\Domain\Ai\Mcp\KiteDeskServer;
use App\Http\Middleware\EnsureMcpEnabled;
use App\Http\Middleware\EnsureUserIsStaff;
use App\Http\Middleware\ResolveMcpUser;
use Laravel\Mcp\Facades\Mcp;

// OAuth discovery and dynamic client registration for MCP clients (Passport issues the tokens).
Mcp::oauthRoutes();

// Staff sign in either through OAuth (the `mcp` guard) or with an admin-issued API token.
Mcp::web('/mcp', KiteDeskServer::class)
    ->middleware([EnsureMcpEnabled::class, 'auth:sanctum,mcp', ResolveMcpUser::class, 'active', EnsureUserIsStaff::class, 'throttle:api'])
    ->name('mcp');
