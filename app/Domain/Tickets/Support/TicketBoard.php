<?php

namespace App\Domain\Tickets\Support;

use App\Domain\Accounts\Models\Group;
use App\Domain\Tickets\Enums\TicketPriority;
use App\Domain\Tickets\Models\CustomStatus;
use App\Domain\Tickets\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as BaseCollection;

/**
 * Splits a ticket queue into kanban lanes by status, priority, assignee or group.
 *
 * Every lane gets its total count and its first tickets; more are loaded per lane. The lane
 * key is the value moved tickets get: a custom status id, a priority, a user or group id, or
 * `none` for unassigned / no group.
 */
class TicketBoard
{
    public const int PER_LANE = 50;

    public const string NONE = 'none';

    /**
     * The ticket column each grouping reads and sets.
     */
    private const array COLUMNS = [
        'status' => 'ticket_status_id',
        'priority' => 'priority',
        'assignee' => 'assignee_id',
        'group' => 'group_id',
    ];

    /**
     * Lanes in the agent's order, with the first tickets of each visible lane, plus every
     * lane (hidden ones too) for the board settings.
     *
     * @param  Builder<Ticket>  $query  The filtered, sorted queue.
     * @param  list<string>  $with  Relations each card needs.
     * @return array{group_by: string, lanes: list<array<string, mixed>>, available: list<array<string, mixed>>}
     */
    public static function build(Builder $query, BoardPreferences $preferences, array $with): array
    {
        $groupBy = $preferences->groupBy;
        $counts = self::counts($query, $groupBy);
        $lanes = self::ordered(self::lanes($groupBy, $counts), $preferences->lanes($groupBy)['order']);
        $hidden = $preferences->lanes($groupBy)['hidden'];

        return [
            'group_by' => $groupBy,
            'lanes' => array_values($lanes
                ->reject(fn (array $lane): bool => in_array($lane['key'], $hidden, true))
                ->map(function (array $lane) use ($query, $groupBy, $counts, $with): array {
                    $count = $counts[$lane['key']] ?? 0;
                    $tickets = $count === 0 ? new Collection : self::tickets($query, $groupBy, $lane['key'], 0, $with);

                    return [...$lane, 'count' => $count, 'has_more' => $count > self::PER_LANE, 'tickets' => $tickets];
                })
                ->all()),
            'available' => array_values($lanes->map(fn (array $lane): array => [...$lane, 'hidden' => in_array($lane['key'], $hidden, true)])->all()),
        ];
    }

    /**
     * One lane's tickets, a page at a time.
     *
     * @param  Builder<Ticket>  $query
     * @param  list<string>  $with
     * @return Collection<int, Ticket>
     */
    public static function tickets(Builder $query, string $groupBy, string $key, int $offset, array $with): Collection
    {
        $column = self::COLUMNS[$groupBy];
        $value = self::value($groupBy, $key);

        return (clone $query)
            ->when($value === null, fn (Builder $lane) => $lane->whereNull("tickets.{$column}"), fn (Builder $lane) => $lane->where("tickets.{$column}", $value))
            ->with($with)
            ->offset($offset)
            ->limit(self::PER_LANE)
            ->get();
    }

    /**
     * Whether the key names a lane that can exist for the grouping.
     */
    public static function isValidKey(string $groupBy, string $key): bool
    {
        return match ($groupBy) {
            'status' => ctype_digit($key),
            'priority' => TicketPriority::tryFrom($key) !== null,
            'assignee', 'group' => $key === self::NONE || ctype_digit($key),
            default => false,
        };
    }

    private static function value(string $groupBy, string $key): string|int|null
    {
        return match (true) {
            $key === self::NONE => null,
            $groupBy === 'priority' => $key,
            default => (int) $key,
        };
    }

    /**
     * Tickets per lane key.
     *
     * @param  Builder<Ticket>  $query
     * @return array<array-key, int>
     */
    private static function counts(Builder $query, string $groupBy): array
    {
        $column = 'tickets.'.self::COLUMNS[$groupBy];

        return (clone $query)
            ->toBase()
            ->cloneWithout(['columns', 'orders'])
            ->cloneWithoutBindings(['select', 'order'])
            ->selectRaw("{$column} as lane, count(*) as aggregate")
            ->groupBy($column)
            ->get()
            ->mapWithKeys(fn (object $row): array => [$row->lane === null ? self::NONE : (string) $row->lane => (int) $row->aggregate])
            ->all();
    }

    /**
     * Every lane the grouping offers, plus any lane that has tickets but is no longer
     * offered (an inactive status, an agent who can't take tickets).
     *
     * @param  array<array-key, int>  $counts
     * @return BaseCollection<int, array{key: string, label: string, color: string|null}>
     */
    private static function lanes(string $groupBy, array $counts): BaseCollection
    {
        return match ($groupBy) {
            'status' => CustomStatuses::all()
                ->filter(fn (CustomStatus $status): bool => $status->is_active || ($counts[$status->id] ?? 0) > 0)
                ->map(fn (CustomStatus $status): array => ['key' => (string) $status->id, 'label' => $status->label(), 'color' => $status->color])
                ->values(),
            'priority' => collect(array_reverse(TicketPriority::cases()))
                ->map(fn (TicketPriority $priority): array => ['key' => $priority->value, 'label' => $priority->label(), 'color' => null]),
            'assignee' => self::withNone(__('Unassigned'), array_values(User::query()
                ->where(fn (Builder $agents) => $agents->assignable()->orWhereIn('id', self::ids($counts)))
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (User $user): array => ['key' => (string) $user->id, 'label' => $user->name, 'color' => null])
                ->all())),
            'group' => self::withNone(__('No group'), array_values(Group::query()
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (Group $group): array => ['key' => (string) $group->id, 'label' => $group->name, 'color' => null])
                ->all())),
            default => collect(),
        };
    }

    /**
     * @param  list<array{key: string, label: string, color: string|null}>  $lanes
     * @return BaseCollection<int, array{key: string, label: string, color: string|null}>
     */
    private static function withNone(string $label, array $lanes): BaseCollection
    {
        return collect([['key' => self::NONE, 'label' => $label, 'color' => null], ...$lanes]);
    }

    /**
     * The agent's order first; lanes added since then keep their default place at the end.
     *
     * @param  BaseCollection<int, array{key: string, label: string, color: string|null}>  $lanes
     * @param  list<string>  $order
     * @return BaseCollection<int, array{key: string, label: string, color: string|null}>
     */
    private static function ordered(BaseCollection $lanes, array $order): BaseCollection
    {
        $position = array_flip($order);

        return $lanes
            ->values()
            ->sortBy(fn (array $lane, int $index): array => [$position[$lane['key']] ?? PHP_INT_MAX, $index])
            ->values();
    }

    /**
     * @param  array<array-key, int>  $counts
     * @return list<int>
     */
    private static function ids(array $counts): array
    {
        return array_values(array_filter(array_keys($counts), is_int(...)));
    }
}
