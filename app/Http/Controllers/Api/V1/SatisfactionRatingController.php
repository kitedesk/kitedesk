<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Tickets\Models\SatisfactionRating;
use App\Domain\Tickets\Models\Ticket;
use App\Http\Resources\Api\V1\SatisfactionRatingResource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Date;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * @tags Satisfaction
 */
class SatisfactionRatingController extends ApiController
{
    /**
     * List satisfaction ratings.
     *
     * Ratings customers gave on tickets the token owner can see, newest first. Filter with
     * `filter[score]`, `filter[ticket_id]` or `filter[rated_since]` (ISO 8601).
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $ratings = QueryBuilder::for(SatisfactionRating::query()
            ->whereNotNull('score')
            ->whereHas('ticket', fn (Builder $query) => $query->whereIn('tickets.id', Ticket::query()->visibleTo($request->user())->select('tickets.id')))
            ->with('user'))
            ->allowedFilters(
                AllowedFilter::exact('score'),
                AllowedFilter::exact('ticket_id'),
                AllowedFilter::callback('rated_since', fn (Builder $query, mixed $value) => $query->where('rated_at', '>=', Date::parse((string) $value))),
            )
            ->allowedSorts('rated_at', 'id')
            ->defaultSort('-rated_at', '-id')
            ->paginate($this->perPage($request))
            ->withQueryString();

        return SatisfactionRatingResource::collection($ratings);
    }
}
