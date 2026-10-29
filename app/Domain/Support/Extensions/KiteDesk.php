<?php

namespace App\Domain\Support\Extensions;

use App\Domain\Accounts\Support\PermissionDefinition;
use App\Domain\Accounts\Support\PermissionRegistry;
use App\Domain\Api\Support\ApiAbilityDefinition;
use App\Domain\Api\Support\ApiAbilityRegistry;
use Closure;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Once;
use Illuminate\View\FileViewFinder;
use Inertia\Inertia;
use Laravel\Mcp\Server\Tool;
use Spatie\Permission\PermissionRegistrar;

/**
 * Where first-party KiteDesk packages hook into the core, from their service provider's
 * `boot()`. Anything else a package needs (listeners, schedules, migrations, bindings) uses
 * Laravel's own APIs.
 *
 * Third-party plugins are not supported: only packages under `vendor/kitedesk/` have their
 * frontend built into the app (see `resources/js/lib/extensions.ts`).
 */
class KiteDesk
{
    /**
     * @var array{admin: list<array{0: Closure|string, 1: string|null}>, agent: list<array{0: Closure|string, 1: string|null}>, portal: list<array{0: Closure|string, 1: string|null}>, api: list<array{0: Closure|string, 1: string|null}>, settings: list<array{0: Closure|string, 1: string|null}>}
     */
    private array $routes = ['admin' => [], 'agent' => [], 'portal' => [], 'api' => [], 'settings' => []];

    /**
     * @var list<array{0: class-string<Tool>, 1: string|null}>
     */
    private array $mcpTools = [];

    /**
     * @var list<string>
     */
    private array $translationPaths = [];

    /**
     * Registers a module admins can switch on and off from the admin center. Its routes,
     * navigation and permissions only work while it is enabled: pass its id to `adminRoutes()`
     * and `settingsRoutes()`, and list its permissions in the definition. Listeners, jobs and
     * anything else a module adds check `ModuleRegistry::isEnabled()` themselves.
     */
    public static function module(ModuleDefinition $module): void
    {
        app(ModuleRegistry::class)->register($module);
    }

    /**
     * Routes for the admin center, loaded inside its group (prefix "admin", name "admin.",
     * middleware `auth, verified, admin`). Guard them with an `admin.*` permission.
     *
     * @param  Closure|string  $routes  a closure that registers routes, or a route file
     * @param  string|null  $module  the module the routes belong to; they answer 404 while it is disabled
     */
    public static function adminRoutes(Closure|string $routes, ?string $module = null): void
    {
        app(self::class)->routes['admin'][] = [$routes, $module];
    }

    /**
     * Routes for the agent workspace, loaded inside its group (prefix "agent", name "agent.",
     * middleware `auth, verified, staff`). Guard them with the permission they need.
     *
     * @param  Closure|string  $routes  a closure that registers routes, or a route file
     * @param  string|null  $module  the module the routes belong to; they answer 404 while it is disabled
     */
    public static function agentRoutes(Closure|string $routes, ?string $module = null): void
    {
        app(self::class)->routes['agent'][] = [$routes, $module];
    }

    /**
     * Routes for the customer portal, loaded inside its group (prefix "portal", name "portal.",
     * middleware `auth, verified`). Staff pass this group too, so check what the customer may
     * see (usually through their organization) in the controller.
     *
     * @param  Closure|string  $routes  a closure that registers routes, or a route file
     * @param  string|null  $module  the module the routes belong to; they answer 404 while it is disabled
     */
    public static function portalRoutes(Closure|string $routes, ?string $module = null): void
    {
        app(self::class)->routes['portal'][] = [$routes, $module];
    }

    /**
     * Routes for the REST API, loaded inside the `v1` group (prefix "api/v1", name "api.v1.",
     * token authentication, staff only, rate limited). Guard each with `api.ability:{ability}` for an
     * ability registered through `apiAbilities()`.
     *
     * @param  Closure|string  $routes  a closure that registers routes, or a route file
     * @param  string|null  $module  the module the routes belong to; they answer 404 while it is disabled
     */
    public static function apiRoutes(Closure|string $routes, ?string $module = null): void
    {
        app(self::class)->routes['api'][] = [$routes, $module];
    }

    /**
     * Token abilities the package adds. Name them `{area}:{read|write}`; give the module so they
     * are only offered and honoured while it is enabled.
     */
    public static function apiAbilities(ApiAbilityDefinition ...$definitions): void
    {
        app(ApiAbilityRegistry::class)->register(...$definitions);
    }

    /**
     * Tools the package adds to the MCP server. Each decides in `shouldRegister()` whether the
     * caller may use it (see `McpAccess::allows()`); a module's tools are left out while it is off.
     *
     * @param  class-string<Tool>  $tool
     * @param  string|null  $module  the module the tool belongs to
     */
    public static function mcpTool(string $tool, ?string $module = null): void
    {
        app(self::class)->mcpTools[] = [$tool, $module];
    }

    /**
     * The package tools that are available now.
     *
     * @return list<class-string<Tool>>
     */
    public static function mcpTools(): array
    {
        $modules = app(ModuleRegistry::class);

        return array_values(array_map(
            fn (array $entry): string => $entry[0],
            array_filter(app(self::class)->mcpTools, fn (array $entry): bool => $entry[1] === null || $modules->isEnabled($entry[1])),
        ));
    }

    /**
     * Routes for the personal settings area, loaded inside `auth, verified`.
     *
     * @param  Closure|string  $routes  a closure that registers routes, or a route file
     * @param  string|null  $module  the module the routes belong to; they answer 404 while it is disabled
     */
    public static function settingsRoutes(Closure|string $routes, ?string $module = null): void
    {
        app(self::class)->routes['settings'][] = [$routes, $module];
    }

    /**
     * Called from the core route files to load what packages registered for an area.
     *
     * @param  'admin'|'agent'|'portal'|'api'|'settings'  $area
     */
    public static function loadRoutes(string $area): void
    {
        foreach (app(self::class)->routes[$area] as [$routes, $module]) {
            $load = function () use ($routes): void {
                if ($routes instanceof Closure) {
                    $routes();
                } else {
                    require $routes;
                }
            };

            $module === null ? $load() : Route::middleware("module:{$module}")->group($load);
        }
    }

    /**
     * Permissions the package adds to the role form. Run `RoleCatalog::sync()` from one of the
     * package's migrations so existing installations store them.
     */
    public static function permissions(PermissionDefinition ...$definitions): void
    {
        app(PermissionRegistry::class)->register(...$definitions);
    }

    /**
     * Inertia pages shipped by the package, rendered as `{namespace}::path`. The frontend
     * finds them under `vendor/kitedesk/{namespace}/resources/js/pages`.
     */
    public static function pages(string $namespace, string $path): void
    {
        App::extend('inertia.view-finder', function (FileViewFinder $finder) use ($namespace, $path): FileViewFinder {
            $finder->addNamespace($namespace, $path);

            return $finder;
        });
    }

    /**
     * The Vite entry for a page component, for the first page load's preload tag: core pages
     * live in `resources/js/pages`, package pages (`namespace::path`) in the package.
     */
    public static function pageEntry(string $component): string
    {
        if (! str_contains($component, '::')) {
            return "resources/js/pages/{$component}.tsx";
        }

        // Vite keys the manifest by the real path, which differs for symlinked path repositories.
        $path = app('inertia.view-finder')->find($component);
        $path = realpath($path) ?: $path;
        $root = (realpath(base_path()) ?: base_path()).DIRECTORY_SEPARATOR;

        return str_starts_with($path, $root) ? substr($path, strlen($root)) : $path;
    }

    /**
     * A folder of `{locale}.json` files, used by `__()` and sent to the frontend.
     */
    public static function translations(string $path): void
    {
        app('translator')->addJsonPath($path);
        app(self::class)->translationPaths[] = $path;
    }

    /**
     * @return list<string>
     */
    public static function translationPaths(): array
    {
        return app(self::class)->translationPaths;
    }

    /**
     * Forget everything remembered for the current installation: scoped instances (branding,
     * custom statuses, workflow origin), `once()` values, the loaded permissions and which
     * modules are enabled. The hosted
     * edition calls it when it switches workspace inside one process.
     */
    public static function flushState(): void
    {
        App::forgetScopedInstances();
        Once::flush();
        app(ModuleRegistry::class)->flush();
        app(PermissionRegistrar::class)->clearPermissionsCollection();
    }

    /**
     * A prop shared with every page.
     */
    public static function share(string $key, mixed $value): void
    {
        Inertia::share($key, $value);
    }
}
