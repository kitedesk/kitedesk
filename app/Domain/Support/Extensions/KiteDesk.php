<?php

namespace App\Domain\Support\Extensions;

use App\Domain\Accounts\Support\PermissionDefinition;
use App\Domain\Accounts\Support\PermissionRegistry;
use Closure;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Once;
use Illuminate\View\FileViewFinder;
use Inertia\Inertia;
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
     * @var array{admin: list<Closure|string>, settings: list<Closure|string>}
     */
    private array $routes = ['admin' => [], 'settings' => []];

    /**
     * @var list<string>
     */
    private array $translationPaths = [];

    /**
     * Routes for the admin center, loaded inside its group (prefix "admin", name "admin.",
     * middleware `auth, verified, admin`). Guard them with an `admin.*` permission.
     *
     * @param  Closure|string  $routes  a closure that registers routes, or a route file
     */
    public static function adminRoutes(Closure|string $routes): void
    {
        app(self::class)->routes['admin'][] = $routes;
    }

    /**
     * Routes for the personal settings area, loaded inside `auth, verified`.
     *
     * @param  Closure|string  $routes  a closure that registers routes, or a route file
     */
    public static function settingsRoutes(Closure|string $routes): void
    {
        app(self::class)->routes['settings'][] = $routes;
    }

    /**
     * Called from the core route files to load what packages registered for an area.
     *
     * @param  'admin'|'settings'  $area
     */
    public static function loadRoutes(string $area): void
    {
        foreach (app(self::class)->routes[$area] as $routes) {
            if ($routes instanceof Closure) {
                $routes();
            } else {
                require $routes;
            }
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
     * custom statuses, workflow origin), `once()` values and the loaded permissions. The hosted
     * edition calls it when it switches workspace inside one process.
     */
    public static function flushState(): void
    {
        App::forgetScopedInstances();
        Once::flush();
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
