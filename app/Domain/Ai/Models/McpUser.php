<?php

namespace App\Domain\Ai\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Passport\Contracts\OAuthenticatable;
use Laravel\Passport\HasApiTokens;

/**
 * A staff member as Passport sees them, behind the `mcp` guard for OAuth-connected MCP clients.
 *
 * User keeps Sanctum's HasApiTokens for the REST API, and Passport's trait can't sit next to it
 * (same property and method names). So Passport authenticates this minimal model on the users
 * table, and the ResolveMcpUser middleware swaps in the real User for everything after it.
 */
class McpUser extends Authenticatable implements OAuthenticatable
{
    use HasApiTokens;

    protected $table = 'users';
}
