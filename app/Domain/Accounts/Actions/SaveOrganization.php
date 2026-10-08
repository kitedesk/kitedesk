<?php

namespace App\Domain\Accounts\Actions;

use App\Domain\Accounts\Models\Organization;
use App\Domain\Webhooks\Enums\WebhookEvent;
use App\Domain\Webhooks\Support\Webhooks;
use App\Http\Resources\Api\V1\OrganizationResource;

/**
 * Creates or updates an organization (from the admin center or the API) and tells webhooks.
 */
class SaveOrganization
{
    /**
     * @param  array<string, mixed>  $attributes  Validated by `SaveOrganizationRequest`.
     */
    public function create(array $attributes): Organization
    {
        $organization = Organization::query()->create($attributes);

        Webhooks::send(WebhookEvent::OrganizationCreated, fn (): array => ['organization' => (new OrganizationResource($organization))->resolve()]);

        return $organization;
    }

    /**
     * @param  array<string, mixed>  $attributes  Validated by `SaveOrganizationRequest`.
     */
    public function update(Organization $organization, array $attributes): Organization
    {
        $before = $organization->only(array_keys($attributes));
        $organization->update($attributes);
        $after = $organization->only(array_keys($attributes));
        $changed = array_keys(array_filter($after, fn (mixed $value, string $field): bool => $value !== $before[$field], ARRAY_FILTER_USE_BOTH));

        if ($changed !== []) {
            Webhooks::send(
                WebhookEvent::OrganizationUpdated,
                fn (): array => ['organization' => (new OrganizationResource($organization))->resolve()],
                array_map(fn (string $field): array => ['from' => $before[$field], 'to' => $after[$field]], array_combine($changed, $changed)),
            );
        }

        return $organization;
    }
}
