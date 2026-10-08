<?php

namespace App\Domain\Tickets\Support;

use App\Domain\Accounts\Enums\TicketAccess;
use App\Domain\Tickets\Enums\TicketStatus;
use App\Domain\Tickets\Models\CustomStatus;
use App\Domain\Tickets\Models\SavedView;
use App\Domain\Tickets\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Predefined ticket queues for the agent workspace (Zendesk "views"), plus one queue per
 * custom status (Freshdesk-style).
 */
class TicketViews
{
    public const DEFAULT = 'unassigned';

    /**
     * View names in English; they are translated when shown (see `summary()`).
     *
     * @var array<string, string>
     */
    public const LABELS = [
        'mine' => 'Your unsolved tickets',
        'unassigned' => 'Unassigned tickets',
        'groups' => 'Your groups',
        'all' => 'All unsolved tickets',
        'sla' => 'SLA breaching',
        'pending' => 'Pending tickets',
        'solved' => 'Recently solved',
    ];

    /**
     * Views whose tickets don't depend on who is looking, so every agent can share one count.
     */
    private const array SHARED_VIEWS = ['unassigned', 'all', 'sla', 'pending', 'solved'];

    /**
     * Changes whenever a ticket is saved, retiring the shared counts.
     */
    private const string COUNTS_VERSION_KEY = 'ticket-views:counts-version';

    /**
     * Shared counts are reused this long at most, for views that change with the clock ("sla").
     */
    private const int SHARED_COUNTS_SECONDS = 60;

    /**
     * A built-in view key, `status:{id}` for every ticket in a custom status, or
     * `saved:{id}` for a saved view the user can see.
     */
    public static function resolve(?string $view, ?User $user = null): string
    {
        if ($user !== null && self::savedView((string) $view, $user) !== null) {
            return (string) $view;
        }

        if (self::customStatus((string) $view) !== null) {
            return (string) $view;
        }

        return self::isBuiltIn($view) ? (string) $view : self::DEFAULT;
    }

    /**
     * The custom status behind a `status:{id}` key.
     */
    public static function customStatus(string $view): ?CustomStatus
    {
        return preg_match('/^status:(\d+)$/', $view, $matches) ? CustomStatuses::find((int) $matches[1]) : null;
    }

    public static function isBuiltIn(?string $view): bool
    {
        return array_key_exists((string) $view, self::LABELS);
    }

    /**
     * The saved view behind a `saved:{id}` key, when the user may see it.
     */
    public static function savedView(string $view, User $user): ?SavedView
    {
        if (! preg_match('/^saved:(\d+)$/', $view, $matches)) {
            return null;
        }

        return SavedView::query()->visibleTo($user)->find((int) $matches[1]);
    }

    /**
     * @return Builder<Ticket>
     */
    public static function query(string $view, User $user): Builder
    {
        $saved = self::savedView($view, $user);

        if ($saved !== null) {
            return TicketFilters::apply(self::query(self::resolve($saved->filters['view'] ?? 'all'), $user), $saved->filters, $user);
        }

        $query = Ticket::query()->visibleTo($user);
        $status = self::customStatus($view);

        if ($status !== null) {
            return $query->where('ticket_status_id', $status->id);
        }

        return match ($view) {
            'mine' => $query->unresolved()->where('assignee_id', $user->id),
            'unassigned' => $query->unresolved()->whereNull('assignee_id'),
            'groups' => $query->unresolved()->whereIn('group_id', $user->groups()->select('groups.id')),
            'sla' => $query->unresolved()->where(fn (Builder $due) => $due
                ->where('first_response_due_at', '<=', now())
                ->orWhere('next_reply_due_at', '<=', now())
                ->orWhere('resolution_due_at', '<=', now())),
            'pending' => $query->whereIn('status', [TicketStatus::Pending, TicketStatus::OnHold]),
            'solved' => $query->whereIn('status', [TicketStatus::Solved, TicketStatus::Closed])->where('solved_at', '>=', now()->subDays(7)),
            default => $query->unresolved(),
        };
    }

    /**
     * Ticket counts for each built-in and saved view, shown in the agent sidebar.
     *
     * @return list<array{key: string, label: string, count: int, saved?: array{id: int, shared: bool, manageable: bool, layout: string|null}, status?: array{id: int, color: string, category: string}}>
     */
    public static function summary(User $user): array
    {
        $shared = $user->ticketAccess() === TicketAccess::All ? self::sharedCounts($user) : [];

        $builtIn = collect(self::LABELS)
            ->map(fn (string $label, string $key): array => [
                'key' => $key,
                'label' => __($label),
                'count' => $shared[$key] ?? self::query($key, $user)->count(),
            ])
            ->values();

        $saved = SavedView::query()
            ->visibleTo($user)
            ->ordered()
            ->get()
            ->map(fn (SavedView $view): array => [
                'key' => $view->key(),
                'label' => $view->name,
                'count' => self::query($view->key(), $user)->count(),
                'saved' => ['id' => $view->id, 'shared' => $view->is_shared, 'manageable' => $view->isManageableBy($user), 'layout' => $view->layout],
            ]);

        $statusCounts = self::statusCounts($user);
        $statuses = CustomStatuses::active()->map(fn (CustomStatus $status): array => [
            'key' => 'status:'.$status->id,
            'label' => $status->label(),
            'count' => $statusCounts[$status->id] ?? 0,
            'status' => ['id' => $status->id, 'color' => $status->color, 'category' => $status->category->value],
        ]);

        return [...$builtIn->all(), ...$saved->all(), ...$statuses->all()];
    }

    /**
     * Tickets the user can see per custom status, in one query. Agents who see every ticket
     * share the result until a ticket changes.
     *
     * @return array<int, int>
     */
    private static function statusCounts(User $user): array
    {
        $count = fn (): array => Ticket::query()
            ->visibleTo($user)
            ->toBase()
            ->selectRaw('ticket_status_id, count(*) as aggregate')
            ->groupBy('ticket_status_id')
            ->pluck('aggregate', 'ticket_status_id')
            ->map(fn (mixed $total): int => (int) $total)
            ->all();

        if ($user->ticketAccess() !== TicketAccess::All) {
            return $count();
        }

        $version = (string) Cache::get(self::COUNTS_VERSION_KEY, 'initial');

        return Cache::remember("ticket-views:status-counts:{$version}", self::SHARED_COUNTS_SECONDS, $count);
    }

    /**
     * Retire the cached shared counts (called whenever a ticket changes).
     */
    public static function forgetCounts(): void
    {
        Cache::forever(self::COUNTS_VERSION_KEY, Str::random(12));
    }

    /**
     * Counts of the views every agent with access to all tickets sees the same way, computed
     * once per ticket change rather than once per agent.
     *
     * @return array<string, int>
     */
    private static function sharedCounts(User $user): array
    {
        $version = (string) Cache::get(self::COUNTS_VERSION_KEY, 'initial');

        return Cache::remember("ticket-views:counts:{$version}", self::SHARED_COUNTS_SECONDS, fn (): array => collect(self::SHARED_VIEWS)
            ->mapWithKeys(fn (string $key): array => [$key => self::query($key, $user)->count()])
            ->all());
    }
}
