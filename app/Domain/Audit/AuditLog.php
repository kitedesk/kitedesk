<?php

namespace App\Domain\Audit;

use App\Domain\Secrets\Models\Secret;
use App\Domain\Tickets\Models\SatisfactionRating;
use App\Domain\Tickets\Models\Ticket;
use App\Domain\Tickets\Models\TicketMessage;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;

/**
 * The audit log is the activity log: everything recorded is filed under a category, with the
 * IP address and browser it came from.
 */
final class AuditLog
{
    /**
     * Categories the audit log can be filtered by (the activity's log name).
     *
     * @var list<string>
     */
    public const array CATEGORIES = ['auth', 'users', 'admin', 'settings', 'tickets'];

    /**
     * @var array<string, Closure(Request): array<string, mixed>>
     */
    private static array $context = [];

    /**
     * Add details to everything recorded during a request, e.g. that KiteDesk support was
     * signed in as the person. Registering the same name again replaces it.
     *
     * @param  Closure(Request): array<string, mixed>  $callback
     */
    public static function addContext(string $name, Closure $callback): void
    {
        self::$context[$name] = $callback;
    }

    /**
     * The categories with their labels.
     *
     * @return array<string, string>
     */
    public static function categories(): array
    {
        return [
            'auth' => __('Sign-in and security'),
            'users' => __('Users'),
            'admin' => __('Admin center'),
            'settings' => __('Settings'),
            'tickets' => __('Tickets'),
        ];
    }

    /**
     * Completes an activity before it is saved.
     *
     * @param  Collection<array-key, mixed>  $properties
     */
    public static function prepare(Activity $activity, Collection $properties, Request $request): void
    {
        if (in_array($activity->log_name, [null, 'default'], true)) {
            $activity->log_name = self::categoryFor($activity->subject_type) ?? $activity->log_name;
        }

        if ($request->ip() !== null && ! $properties->has('ip')) {
            $properties->put('ip', $request->ip());
            $properties->put('user_agent', Str::limit((string) $request->userAgent(), 255, ''));
        }

        foreach (self::$context as $callback) {
            $properties = $properties->merge($callback($request));
        }

        $activity->properties = $properties;
    }

    private static function categoryFor(?string $subjectType): ?string
    {
        return match ($subjectType) {
            Ticket::class, TicketMessage::class, Secret::class, SatisfactionRating::class => 'tickets',
            User::class => 'users',
            default => null,
        };
    }
}
