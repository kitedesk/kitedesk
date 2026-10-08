<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Accounts\Actions\SaveOrganization;
use App\Domain\Accounts\Models\Organization;
use App\Http\Requests\Admin\SaveOrganizationRequest;
use App\Http\Resources\Api\V1\OrganizationResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * @tags Organizations
 */
class OrganizationController extends ApiController
{
    /**
     * List organizations.
     *
     * Filter with `filter[search]` (name) or `filter[updated_since]` (ISO 8601).
     * Sort with `sort=name|created_at|updated_at|id` (prefix `-` for descending).
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $organizations = QueryBuilder::for(Organization::query()->withCount('members'))
            ->allowedFilters(AllowedFilter::partial('search', 'name'), $this->updatedSince())
            ->allowedSorts('name', 'created_at', 'updated_at', 'id')
            ->defaultSort('name', 'id')
            ->paginate($this->perPage($request))
            ->withQueryString();

        return OrganizationResource::collection($organizations);
    }

    /**
     * Show an organization.
     */
    public function show(Organization $organization): OrganizationResource
    {
        return new OrganizationResource($organization->loadCount('members'));
    }

    /**
     * Create an organization.
     *
     * People whose email matches one of its `domains` join it when they first write in.
     */
    public function store(SaveOrganizationRequest $request, SaveOrganization $saveOrganization): JsonResponse
    {
        $organization = $saveOrganization->create($request->validated());

        return (new OrganizationResource($organization->loadCount('members')))->response()->setStatusCode(201);
    }

    /**
     * Update an organization.
     *
     * Send the full organization, as when creating.
     */
    public function update(SaveOrganizationRequest $request, Organization $organization, SaveOrganization $saveOrganization): OrganizationResource
    {
        $saveOrganization->update($organization, $request->validated());

        return new OrganizationResource($organization->loadCount('members'));
    }

    /**
     * Delete an organization.
     *
     * Its members and tickets stay, without an organization.
     */
    public function destroy(Organization $organization): Response
    {
        $organization->delete();

        return response()->noContent();
    }
}
