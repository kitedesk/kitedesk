<?php

/*
 * Runs after Larastan boots the app. Larastan types `$request->user()` as every auth
 * provider's model, but the `mcp` guard's McpUser never reaches a controller:
 * App\Http\Middleware\ResolveMcpUser swaps in the real User first. So analysis only
 * sees the guards whose users the app code handles.
 */
app('config')->set('auth.guards', array_intersect_key((array) app('config')->get('auth.guards'), ['web' => true, 'sanctum' => true]));
