<?php

namespace App\Domain\Accounts\Actions;

use App\Domain\Accounts\Enums\OrganizationRole;
use App\Domain\Accounts\Models\Organization;
use App\Domain\Tickets\Enums\TicketStatus;
use App\Domain\Tickets\Models\Ticket;
use App\Domain\Webhooks\Enums\WebhookEvent;
use App\Domain\Webhooks\Support\Webhooks;
use App\Http\Resources\Api\V1\UserResource;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Confirms which organization a customer belongs to, and whether they may act for it, or
 * takes them out of it. Their unresolved tickets follow; solved and closed ones keep the
 * organization they had.
 */
class LinkToOrganization
{
    /**
     * Links (or, with no organization, unlinks) the customer, then records it in the audit
     * log and tells webhooks. Nothing happens when nothing changes.
     */
    public function handle(User $customer, ?Organization $organization, ?OrganizationRole $role, User $actor): User
    {
        $before = $this->audited($customer);

        if (! $this->apply($customer, $organization, $role, $actor)) {
            return $customer;
        }

        $after = $this->audited($customer->load('organization:id,name'));
        $changed = array_keys(array_filter($after, fn (?string $value, string $field): bool => $value !== $before[$field], ARRAY_FILTER_USE_BOTH));

        activity()->performedOn($customer)->causedBy($actor)->event('updated')
            ->withChanges(['old' => array_intersect_key($before, array_flip($changed)), 'attributes' => array_intersect_key($after, array_flip($changed))])
            ->log('updated');

        Webhooks::send(WebhookEvent::UserUpdated, fn (): array => ['user' => (new UserResource($customer))->resolve()], array_map(
            fn (string $field): array => ['from' => $before[$field], 'to' => $after[$field]],
            array_combine($changed, $changed),
        ));

        return $customer;
    }

    /**
     * Saves the change without recording it, for callers that record a larger change themselves
     * (the user form). A role without an organization is dropped; an organization without a
     * role makes them a member.
     *
     * @return bool whether anything changed
     */
    public function apply(User $customer, ?Organization $organization, ?OrganizationRole $role, User $actor): bool
    {
        $organizationId = $organization?->id;
        $role = $organization !== null ? ($role ?? OrganizationRole::Member) : null;
        $organizationChanged = $customer->organization_id !== $organizationId;

        if (! $organizationChanged && $customer->organization_role === $role) {
            return false;
        }

        DB::transaction(function () use ($customer, $organizationId, $role, $organizationChanged, $actor): void {
            $customer->forceFill([
                'organization_id' => $organizationId,
                'organization_role' => $role,
                'organization_linked_at' => $organizationId !== null ? now() : null,
                'organization_linked_by' => $organizationId !== null ? $actor->id : null,
            ])->save();

            if ($organizationChanged) {
                Ticket::query()
                    ->where('requester_id', $customer->id)
                    ->whereIn('status', array_map(fn (TicketStatus $status): string => $status->value, TicketStatus::unresolved()))
                    ->update(['organization_id' => $organizationId]);
            }
        });

        return true;
    }

    /**
     * @return array{organization: string|null, organization_role: string|null}
     */
    private function audited(User $customer): array
    {
        return [
            'organization' => $customer->organization?->name,
            'organization_role' => $customer->organization_id !== null ? $customer->organization_role?->label() : null,
        ];
    }
}
