<?php

namespace App\Http\Middleware;

use App\Domain\Support\Extensions\ModuleRegistry;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * `module:{id}`: routes of a module that is not enabled don't exist.
 */
class EnsureModuleEnabled
{
    public function __construct(private readonly ModuleRegistry $modules) {}

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string $module): Response
    {
        abort_unless($this->modules->isEnabled($module), Response::HTTP_NOT_FOUND);

        return $next($request);
    }
}
