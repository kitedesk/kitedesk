<?php

namespace App\Http\Middleware;

use App\Domain\Accounts\Support\PermissionRegistry;
use App\Domain\Branding\Branding;
use App\Domain\Entitlements\Support\PlanLimits;
use App\Domain\Support\Broadcasting\Channels;
use App\Domain\Support\Extensions\KiteDesk;
use App\Domain\Tickets\Support\GuestAccess;
use App\Domain\Tickets\Support\TicketViews;
use App\Rules\Turnstile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Inertia\Inertia;
use Inertia\Middleware;
use Inertia\OnceProp;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => Branding::current()->name(),
            'branding' => $this->branding(),
            'auth' => $this->auth($request),
            'agentNav' => fn () => $request->user()?->isStaff() ? [
                'views' => TicketViews::summary($request->user()),
                'unreadNotifications' => $request->user()->unreadNotifications()->count(),
            ] : null,
            'entitlements' => fn (): array => PlanLimits::sharedProps(),
            'broadcastScope' => Channels::scope(),
            'locale' => App::getLocale(),
            'translations' => $this->translations(),
            'guestTickets' => GuestAccess::enabled(),
            'captchaSiteKey' => Turnstile::enabled() ? config('services.turnstile.site_key') : null,
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }

    /**
     * The signed-in user plus what their role allows, so pages can hide what they can't use.
     * Customers get no permissions.
     *
     * @return array<string, mixed>
     */
    private function auth(Request $request): array
    {
        $user = $request->user();
        $role = $user?->staffRole();

        return [
            'user' => $user?->makeHidden('roles'),
            'isStaff' => $user?->isStaff() ?? false,
            'role' => $role?->displayName(),
            'ticketAccess' => $user?->isStaff() ? $user->ticketAccess()->value : null,
            'can' => collect(app(PermissionRegistry::class)->names())
                ->mapWithKeys(fn (string $permission): array => [$permission => $user?->hasPermission($permission) ?? false])
                ->all(),
        ];
    }

    /**
     * Logo, colors and help center text, sent once and remembered by the client until an
     * admin changes them.
     */
    private function branding(): OnceProp
    {
        $branding = Branding::current();

        return Inertia::once(fn (): array => $branding->sharedProps())->as("branding.{$branding->version}");
    }

    /**
     * The installation language's JSON strings (APP_LOCALE), plus those of KiteDesk packages,
     * sent once and remembered by the client until a translation file changes. Keys are the
     * English text, so an English installation sends nothing.
     */
    private function translations(): OnceProp
    {
        $locale = App::getLocale();
        $files = array_values(array_filter(
            [lang_path("{$locale}.json"), ...array_map(fn (string $path): string => "{$path}/{$locale}.json", KiteDesk::translationPaths())],
            is_file(...),
        ));
        $version = $files === [] ? '0' : md5(implode('|', array_map(fn (string $file): string => $file.filemtime($file), $files)));

        return Inertia::once(function () use ($files): object {
            $strings = [];

            foreach ($files as $file) {
                $decoded = json_decode((string) file_get_contents($file), true);
                $strings = [...(is_array($decoded) ? $decoded : []), ...$strings];
            }

            return (object) $strings;
        })->as("translations.{$locale}.{$version}");
    }
}
