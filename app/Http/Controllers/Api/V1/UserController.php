<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Accounts\Actions\DeactivateUser;
use App\Domain\Accounts\Actions\ReactivateUser;
use App\Domain\Accounts\Actions\SaveUser;
use App\Http\Requests\Api\V1\SaveUserRequest;
use App\Http\Resources\Api\V1\UserResource;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * @tags Users
 */
class UserController extends ApiController
{
    /**
     * List users.
     *
     * Filter with `filter[type]` (`staff` or `customer`), `filter[organization_id]`, `filter[email]` (exact),
     * `filter[search]` (name/email) or `filter[updated_since]` (ISO 8601).
     * Sort with `sort=name|created_at|updated_at|id` (prefix `-` for descending).
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $users = QueryBuilder::for(User::query()->with(UserResource::RELATIONS))
            ->allowedFilters(
                AllowedFilter::exact('type'),
                AllowedFilter::exact('organization_id'),
                AllowedFilter::exact('email'),
                AllowedFilter::callback('search', fn (Builder $query, mixed $value) => $query->where(fn (Builder $search) => $search
                    ->where('name', 'like', '%'.$value.'%')
                    ->orWhere('email', 'like', '%'.$value.'%'))),
                $this->updatedSince(),
            )
            ->allowedSorts('name', 'created_at', 'updated_at', 'id')
            ->defaultSort('name', 'id')
            ->paginate($this->perPage($request))
            ->withQueryString();

        return UserResource::collection($users);
    }

    /**
     * Show a user.
     */
    public function show(User $user): UserResource
    {
        return new UserResource($user);
    }

    /**
     * Create a user.
     *
     * A customer by default; staff need `type: staff` and a `role_id`, and take a seat on plans
     * that limit them. New people are emailed a link to choose a password unless `invite` is false.
     */
    public function store(SaveUserRequest $request, SaveUser $saveUser): JsonResponse
    {
        $user = $saveUser->create($request->userData(), $request->chosenRole(), $request->user(), invite: $request->boolean('invite', true));

        return (new UserResource($user))->response()->setStatusCode(201);
    }

    /**
     * Update a user.
     *
     * Send the full user, as when creating. `group_ids` is only changed when given.
     */
    public function update(SaveUserRequest $request, User $user, SaveUser $saveUser): UserResource
    {
        return new UserResource($saveUser->update($user, $request->userData(), $request->chosenRole(), $request->user()));
    }

    /**
     * Deactivate a user.
     *
     * They can no longer sign in, call the API or write in; their history is kept.
     */
    public function deactivate(Request $request, User $user, DeactivateUser $deactivate): UserResource
    {
        $deactivate->handle($user, $request->user());

        return new UserResource($user->refresh());
    }

    /**
     * Reactivate a user.
     */
    public function reactivate(Request $request, User $user, ReactivateUser $reactivate): UserResource
    {
        $reactivate->handle($user, $request->user());

        return new UserResource($user->refresh());
    }
}
