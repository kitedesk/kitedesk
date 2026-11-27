<?php

namespace App\Domain\Tickets\Support;

use App\Domain\Tickets\Enums\TicketFieldType;
use App\Domain\Tickets\Models\Ticket;
use App\Domain\Tickets\Models\TicketCategory;
use App\Domain\Tickets\Models\TicketField;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Spatie\QueryBuilder\AllowedFilter;

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
     * Filters on custom fields are named after the field: `field_<key>`.
     */
    public const string FIELD_PREFIX = 'field_';

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
        $fields = self::filterableFields()->keyBy(fn (TicketField $field): string => self::FIELD_PREFIX.$field->key);

        foreach ($filters as $key => $value) {
            if ($value === null || $value === '' || ! self::isKey((string) $key)) {
                continue;
            }

            if ($fields->has($key)) {
                self::customField($query, $fields[$key], $value);

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
     * Whether a saved view may store this filter: a built-in one, or one on a filterable custom field.
     */
    public static function isKey(string $key): bool
    {
        return in_array($key, self::KEYS, true)
            || (str_starts_with($key, self::FIELD_PREFIX) && self::filterableFields()->contains('key', substr($key, strlen(self::FIELD_PREFIX))));
    }

    /**
     * Custom fields the ticket list can be filtered by, looked up once per request.
     *
     * @return Collection<int, TicketField>
     */
    public static function filterableFields(): Collection
    {
        return once(fn (): Collection => TicketField::query()
            ->ordered()
            ->whereIn('type', array_map(fn (TicketFieldType $type): string => $type->value, array_filter(TicketFieldType::cases(), fn (TicketFieldType $type): bool => $type->isFilterable())))
            ->get());
    }

    /**
     * Query-builder filters for every filterable custom field (`filter[field_<key>]`).
     *
     * @return list<AllowedFilter>
     */
    public static function allowedFieldFilters(): array
    {
        return array_values(self::filterableFields()
            ->map(fn (TicketField $field): AllowedFilter => AllowedFilter::callback(
                self::FIELD_PREFIX.$field->key,
                fn (Builder $query, mixed $value) => self::customField($query, $field, $value),
            ))
            ->all());
    }

    /**
     * Drop-downs match any of the given options, multi-selects any ticket that has one of them,
     * and checkboxes "yes" or "no" (unset counts as no).
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     */
    public static function customField(Builder $query, TicketField $field, mixed $value): void
    {
        $column = 'custom_fields->'.$field->key;
        $value = is_bool($value) ? ($value ? 'true' : 'false') : $value;
        $values = array_values(array_filter(
            array_map(trim(...), array_map(strval(...), is_array($value) ? $value : explode(',', (string) $value))),
            fn (string $item): bool => $item !== '',
        ));

        if ($values === []) {
            return;
        }

        match ($field->type) {
            TicketFieldType::Checkbox => filter_var($values[0], FILTER_VALIDATE_BOOLEAN)
                ? $query->where($column, true)
                : $query->where(fn (Builder $unset) => $unset->whereNull($column)->orWhere($column, false)),
            TicketFieldType::MultiSelect => $query->where(function (Builder $any) use ($column, $values): void {
                foreach ($values as $item) {
                    $any->orWhereJsonContains($column, $item);
                }
            }),
            default => $query->whereIn($column, $values),
        };
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
