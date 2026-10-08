<?php

namespace App\Domain\Tickets\Support;

use App\Domain\Tickets\Models\Ticket;
use App\Domain\Tickets\Models\TicketCategory;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

/**
 * Ticket queue filters, shared by the queue's query string and saved views.
 */
class TicketFilters
{
    /**
     * Filter keys a saved view may store.
     */
    public const array KEYS = ['status', 'ticket_status_id', 'priority', 'assignee_id', 'group_id', 'category_id', 'tag', 'search'];

    /**
     * Assignee value meaning "whoever is looking at the view".
     */
    public const string ME = 'me';

    /**
     * A search matching more people than this only looks at the first ones' tickets.
     */
    private const int MAX_SEARCHED_REQUESTERS = 200;

    /**
     * Apply stored filters (as saved by a view) to a ticket query.
     *
     * @param  Builder<Ticket>  $query
     * @param  array<string, mixed>  $filters
     * @return Builder<Ticket>
     */
    public static function apply(Builder $query, array $filters, User $user): Builder
    {
        foreach (Arr::only($filters, self::KEYS) as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            match ($key) {
                'status', 'ticket_status_id', 'priority', 'group_id' => $query->where($key, $value),
                'assignee_id' => self::assignee($query, $value, $user),
                'category_id' => self::category($query, $value),
                'tag' => self::tag($query, $value),
                'search' => self::search($query, $value),
                default => null,
            };
        }

        return $query;
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     */
    public static function assignee(Builder $query, mixed $value, User $user): void
    {
        $query->where('assignee_id', $value === self::ME ? $user->id : (int) $value);
    }

    /**
     * A category also matches its subcategories.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     */
    public static function category(Builder $query, mixed $value): void
    {
        $query->whereIn('category_id', TicketCategory::query()
            ->whereKey((int) $value)
            ->orWhere('parent_id', (int) $value)
            ->select('id'));
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     */
    public static function tag(Builder $query, mixed $value): void
    {
        $query->whereHas('tags', fn (Builder $tags) => $tags->whereIn('name', Arr::wrap($value)));
    }

    /**
     * Subject, `#id`, or requester name/email.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     */
    public static function search(Builder $query, mixed $value): void
    {
        // The query builder splits "a,b" into an array; a search is one phrase.
        $term = trim(is_array($value) ? implode(',', array_map(strval(...), $value)) : (string) $value);
        $id = ltrim($term, '#');

        // Matching people are looked up once, instead of checking every ticket's requester.
        $requesterIds = User::query()
            ->where(fn (Builder $people) => $people->where('name', 'like', '%'.$term.'%')->orWhere('email', 'like', '%'.$term.'%'))
            ->limit(self::MAX_SEARCHED_REQUESTERS)
            ->pluck('id');

        $query->where(fn (Builder $search) => $search
            ->where('subject', 'like', '%'.$term.'%')
            ->orWhere('number', $id)
            ->when(ctype_digit($id), fn (Builder $byId) => $byId->orWhere('id', (int) $id))
            ->when($requesterIds->isNotEmpty(), fn (Builder $byRequester) => $byRequester->orWhereIn('requester_id', $requesterIds)));
    }
}
