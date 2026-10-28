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
     * @var array<string, Closure(): string>
     */
    private static array $extraCategories = [];

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
     * Add a category the audit log can be filtered by: the log name its activity is recorded
     * under (`activity()->useLog($name)`). The label is resolved when shown, so it is translated.
     *
     * @param  Closure(): string  $label
     */
    public static function addCategory(string $name, Closure $label): void
    {
        self::$extraCategories[$name] = $label;
    }

    /**
     * The categories with their labels (the activity's log name).
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
            ...array_map(fn (Closure $label): string => $label(), self::$extraCategories),
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
