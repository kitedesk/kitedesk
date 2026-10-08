<?php

namespace App\Domain\Tickets\Support;

use App\Models\User;
use Illuminate\Support\Arr;

/**
 * How an agent likes to see the ticket queue: as a list or a board, and for the board, what
 * the lanes group by, the order and visibility of lanes (remembered per grouping) and what
 * each card shows. Stored in `users.preferences`.
 */
final class BoardPreferences
{
    public const array LAYOUTS = ['list', 'board'];

    public const array GROUP_BY = ['status', 'priority', 'assignee', 'group'];

    public const array CARD_FIELDS = ['requester', 'assignee', 'priority', 'status', 'group', 'sla', 'tags', 'latest_message', 'updated'];

    public const array DEFAULT_CARD_FIELDS = ['requester', 'assignee', 'priority', 'sla', 'updated'];

    /**
     * @param  'list'|'board'  $layout
     * @param  'status'|'priority'|'assignee'|'group'  $groupBy
     * @param  array<string, array{order: list<string>, hidden: list<string>}>  $columns
     * @param  list<string>  $cardFields
     */
    public function __construct(
        public string $layout = 'list',
        public string $groupBy = 'status',
        public array $columns = [],
        public array $cardFields = self::DEFAULT_CARD_FIELDS,
    ) {}

    public static function for(User $user): self
    {
        $stored = $user->preferences ?? [];
        $board = is_array($stored['board'] ?? null) ? $stored['board'] : [];

        return self::fromArray([
            'layout' => $stored['tickets_layout'] ?? null,
            'group_by' => $board['group_by'] ?? null,
            'columns' => $board['columns'] ?? null,
            'card_fields' => $board['card_fields'] ?? null,
        ]);
    }

    /**
     * Build preferences from untrusted input, dropping anything unknown.
     *
     * @param  array<string, mixed>  $input
     */
    public static function fromArray(array $input): self
    {
        $layout = in_array($input['layout'] ?? null, self::LAYOUTS, true) ? $input['layout'] : 'list';
        $groupBy = in_array($input['group_by'] ?? null, self::GROUP_BY, true) ? $input['group_by'] : 'status';

        $columns = [];

        foreach (Arr::only(is_array($input['columns'] ?? null) ? $input['columns'] : [], self::GROUP_BY) as $key => $lanes) {
            $columns[$key] = [
                'order' => self::keys($lanes['order'] ?? []),
                'hidden' => self::keys($lanes['hidden'] ?? []),
            ];
        }

        $cardFields = is_array($input['card_fields'] ?? null)
            ? array_values(array_intersect(self::CARD_FIELDS, $input['card_fields']))
            : self::DEFAULT_CARD_FIELDS;

        return new self($layout, $groupBy, $columns, $cardFields);
    }

    /**
     * Apply a partial change and save it to the user.
     *
     * @param  array{tickets_layout?: string, group_by?: string, columns?: array<string, mixed>, card_fields?: list<string>, reset?: bool}  $changes
     */
    public static function update(User $user, array $changes): self
    {
        $current = self::for($user);

        $preferences = ($changes['reset'] ?? false)
            ? new self(layout: $current->layout)
            : self::fromArray([
                'layout' => $changes['tickets_layout'] ?? $current->layout,
                'group_by' => $changes['group_by'] ?? $current->groupBy,
                'columns' => [...$current->columns, ...($changes['columns'] ?? [])],
                'card_fields' => $changes['card_fields'] ?? $current->cardFields,
            ]);

        $user->forceFill(['preferences' => [
            ...($user->preferences ?? []),
            'tickets_layout' => $preferences->layout,
            'board' => [
                'group_by' => $preferences->groupBy,
                'columns' => $preferences->columns,
                'card_fields' => $preferences->cardFields,
            ],
        ]])->save();

        return $preferences;
    }

    /**
     * The agent's lane order and hidden lanes for a grouping.
     *
     * @return array{order: list<string>, hidden: list<string>}
     */
    public function lanes(string $groupBy): array
    {
        return $this->columns[$groupBy] ?? ['order' => [], 'hidden' => []];
    }

    /**
     * @return array{layout: string, group_by: string, columns: array<string, array{order: list<string>, hidden: list<string>}>, card_fields: list<string>}
     */
    public function toArray(): array
    {
        return [
            'layout' => $this->layout,
            'group_by' => $this->groupBy,
            'columns' => $this->columns,
            'card_fields' => $this->cardFields,
        ];
    }

    /**
     * @return list<string>
     */
    private static function keys(mixed $keys): array
    {
        return is_array($keys)
            ? array_values(array_unique(array_filter(array_map(fn (mixed $key): string => is_scalar($key) ? substr((string) $key, 0, 40) : '', array_slice($keys, 0, 200)))))
            : [];
    }
}
