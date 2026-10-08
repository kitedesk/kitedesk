<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Accounts\Models\Group;
use App\Domain\Tickets\Models\CustomStatus;
use App\Domain\Tickets\Models\Tag;
use App\Domain\Tickets\Models\TicketCategory;
use App\Domain\Tickets\Models\TicketField;
use App\Domain\Tickets\Models\TicketForm;
use App\Http\Resources\Api\V1\CustomStatusResource;
use App\Http\Resources\Api\V1\GroupResource;
use App\Http\Resources\Api\V1\TagResource;
use App\Http\Resources\Api\V1\TicketCategoryResource;
use App\Http\Resources\Api\V1\TicketFieldResource;
use App\Http\Resources\Api\V1\TicketFormResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * The values ticket properties refer to, so integrations can turn names into IDs. These lists
 * are short and returned whole, without pagination.
 *
 * @tags Ticket setup
 */
class LookupController extends ApiController
{
    /**
     * List groups, with the IDs of their agents.
     */
    public function groups(): AnonymousResourceCollection
    {
        return GroupResource::collection(Group::query()->with('agents:id')->orderBy('name')->orderBy('id')->get());
    }

    /**
     * List statuses.
     *
     * Admin-defined statuses, each tied to a built-in `category` (`new`, `open`, `pending`,
     * `on_hold`, `solved`, `closed`). Set one on a ticket with `ticket_status_id`.
     */
    public function statuses(): AnonymousResourceCollection
    {
        return CustomStatusResource::collection(CustomStatus::query()->ordered()->get());
    }

    /**
     * List categories.
     *
     * Two levels: subcategories have a `parent_id`.
     */
    public function categories(): AnonymousResourceCollection
    {
        return TicketCategoryResource::collection(TicketCategory::query()->ordered()->get());
    }

    /**
     * List custom fields.
     */
    public function fields(): AnonymousResourceCollection
    {
        return TicketFieldResource::collection(TicketField::query()->ordered()->get());
    }

    /**
     * List ticket forms, with their fields.
     */
    public function forms(): AnonymousResourceCollection
    {
        return TicketFormResource::collection(TicketForm::query()->with('fields')->orderBy('name')->orderBy('id')->get());
    }

    /**
     * List tags.
     *
     * Paginated. Filter with `filter[search]`.
     */
    public function tags(Request $request): AnonymousResourceCollection
    {
        return TagResource::collection(
            QueryBuilder::for(Tag::query()->withCount('tickets'))
                ->allowedFilters(AllowedFilter::partial('search', 'name'))
                ->defaultSort('name')
                ->paginate($this->perPage($request, 100))
                ->withQueryString(),
        );
    }
}
