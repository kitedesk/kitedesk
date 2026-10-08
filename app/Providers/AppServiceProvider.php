<?php

namespace App\Providers;

use App\Domain\Accounts\Enums\Permission;
use App\Domain\Accounts\Listeners\RecordLastLogin;
use App\Domain\Accounts\Models\Group;
use App\Domain\Accounts\Support\PermissionRegistry;
use App\Domain\Branding\Branding;
use App\Domain\Entitlements\Contracts\Entitlements;
use App\Domain\Entitlements\UnlimitedEntitlements;
use App\Domain\Mail\Listeners\SendTicketEmails;
use App\Domain\Sla\Events\SlaTargetMissed;
use App\Domain\Support\ErrorPages;
use App\Domain\Support\Extensions\KiteDesk;
use App\Domain\Tickets\Events\MessageCreated;
use App\Domain\Tickets\Events\TicketCreated;
use App\Domain\Tickets\Events\TicketUpdated;
use App\Domain\Tickets\Listeners\SendTicketNotifications;
use App\Domain\Tickets\Models\TicketCategory;
use App\Domain\Tickets\Support\CustomStatuses;
use App\Domain\Webhooks\Listeners\QueueWebhookDeliveries;
use App\Domain\Workflows\Engine\WorkflowOrigin;
use App\Domain\Workflows\Listeners\StartWorkflows;
use App\Domain\Workflows\Support\WorkflowReferences;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Laravel\Passport\Client;
use Laravel\Passport\Passport;
use Spatie\Activitylog\Models\Activity;
use Symfony\Component\HttpFoundation\Response;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(WorkflowOrigin::class);
        $this->app->scoped(CustomStatuses::class);
        $this->app->scoped(Branding::class, fn (): Branding => Branding::load());

        // Filled by KiteDesk packages while booting, so they live as long as the app.
        $this->app->singleton(KiteDesk::class);
        $this->app->singleton(PermissionRegistry::class);

        // Package providers register first, so a KiteDesk package's plan binding wins.
        $this->app->bindIf(Entitlements::class, UnlimitedEntitlements::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->registerDomainListeners();

        Group::deleted(fn (Group $group) => WorkflowReferences::deleted('group', $group->id, $group->name));
        User::deleted(fn (User $user) => WorkflowReferences::deleted('user', $user->id, $user->name));
        TicketCategory::deleted(fn (TicketCategory $category) => WorkflowReferences::deleted('category', $category->id, $category->name));

        // Changes made by a workflow are credited to it in the ticket's activity.
        Activity::creating(function (Activity $activity): void {
            $workflow = app(WorkflowOrigin::class)->workflowName();

            if ($workflow !== null) {
                $activity->properties = collect($activity->properties ?? [])->put('workflow', $workflow);
            }
        });

        $this->configureOAuth();

        Inertia::handleExceptionsUsing(ErrorPages::render(...));

        Gate::define('viewReports', fn (User $user): bool => $user->hasPermission(Permission::ViewReports));
        Gate::define('exportReports', fn (User $user): bool => $user->hasPermission(Permission::ViewReports) && $user->hasPermission(Permission::ExportReports));
    }

    /**
     * OAuth (Passport) is only used by MCP clients. The consent screen is a KiteDesk page, and
     * only staff can connect an app: customers have nothing to share with one.
     */
    protected function configureOAuth(): void
    {
        Passport::tokensExpireIn(CarbonImmutable::now()->addDays(30));
        Passport::refreshTokensExpireIn(CarbonImmutable::now()->addDays(180));

        Passport::authorizationView(function (array $parameters): Response {
            /** @var User $user */
            $user = $parameters['user'];
            /** @var Client $client */
            $client = $parameters['client'];

            abort_unless($user->isStaff(), Response::HTTP_FORBIDDEN);

            return Inertia::render('auth/authorize-app', [
                'client' => ['id' => $client->getKey(), 'name' => $client->name],
                'authToken' => $parameters['authToken'],
                'csrfToken' => csrf_token(),
            ])->toResponse($parameters['request']);
        });
    }

    /**
     * Wire domain events to their listeners (domain modules live outside app/Listeners).
     */
    protected function registerDomainListeners(): void
    {
        Event::listen(Login::class, RecordLastLogin::class);
        Event::listen([TicketCreated::class, TicketUpdated::class, MessageCreated::class], QueueWebhookDeliveries::class);
        Event::listen([TicketCreated::class, TicketUpdated::class, MessageCreated::class], SendTicketNotifications::class);
        Event::listen([TicketCreated::class, TicketUpdated::class], SendTicketEmails::class);
        Event::listen([TicketCreated::class, TicketUpdated::class, MessageCreated::class, SlaTargetMissed::class], StartWorkflows::class);
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
