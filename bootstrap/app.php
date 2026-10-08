<?php

use App\Http\Middleware\AddSecurityHeaders;
use App\Http\Middleware\EnsureApiAbility;
use App\Http\Middleware\EnsureCanAccessAdmin;
use App\Http\Middleware\EnsureFeatureEntitled;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\EnsureUserIsStaff;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SetUserLocale;
use App\Http\Middleware\ThrottleAccountForms;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Spatie\Permission\Middleware\PermissionMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'staff' => EnsureUserIsStaff::class,
            'active' => EnsureUserIsActive::class,
            'admin' => EnsureCanAccessAdmin::class,
            'permission' => PermissionMiddleware::class,
            'entitlement' => EnsureFeatureEntitled::class,
            'api.ability' => EnsureApiAbility::class,
        ]);

        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->append(AddSecurityHeaders::class);

        $middleware->web(append: [
            ThrottleAccountForms::class,
            EnsureUserIsActive::class,
            SetUserLocale::class,
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Secrets must never end up in the session as "old input".
        $exceptions->dontFlash(['secret']);

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
