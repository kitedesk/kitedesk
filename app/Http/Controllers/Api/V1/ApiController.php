<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Spatie\QueryBuilder\AllowedFilter;

/**
 * Shared conventions of the REST API v1 lists: page-based pagination (`per_page` 1–100) and
 * `filter[updated_since]` for incremental syncs.
 */
abstract class ApiController extends Controller
{
    protected function perPage(Request $request, int $default = 25): int
    {
        return min(max($request->integer('per_page', $default), 1), 100);
    }

    /**
     * `filter[updated_since]`: records changed at or after an ISO 8601 time.
     */
    protected function updatedSince(): AllowedFilter
    {
        return AllowedFilter::callback('updated_since', fn (Builder $query, mixed $value) => $query->where($query->qualifyColumn('updated_at'), '>=', Date::parse((string) $value)));
    }
}
