<?php

namespace App\Http\Controllers\Agent;

use App\Domain\Accounts\Actions\LinkToOrganization;
use App\Domain\Accounts\Enums\OrganizationRole;
use App\Domain\Accounts\Models\Organization;
use App\Domain\Tickets\Models\Ticket;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

/**
 * Confirms, from a ticket, which organization its requester belongs to and whether they may
 * act for it. Going through the ticket means agents can only link people whose tickets they see.
 */
class RequesterOrganizationController extends Controller
{
    public function update(Request $request, Ticket $ticket, LinkToOrganization $linkToOrganization): RedirectResponse
    {
        $input = $request->validate([
            'organization_id' => ['nullable', 'integer', Rule::exists('organizations', 'id')],
            'role' => ['nullable', Rule::enum(OrganizationRole::class)],
        ]);

        $requester = $ticket->requester;

        if ($requester->isStaff()) {
            throw ValidationException::withMessages(['organization_id' => __('Team members are not linked to customer organizations.')]);
        }

        $organization = isset($input['organization_id']) ? Organization::query()->findOrFail((int) $input['organization_id']) : null;
        $linkToOrganization->handle($requester, $organization, isset($input['role']) ? OrganizationRole::from($input['role']) : null, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => $organization !== null
            ? __(':name is now linked to :organization.', ['name' => $requester->name, 'organization' => $organization->name])
            : __(':name is no longer linked to an organization.', ['name' => $requester->name])]);

        return back();
    }

    /**
     * Organizations whose name matches, for the picker.
     */
    public function lookup(Request $request): JsonResponse
    {
        $query = trim($request->string('q')->toString());

        return response()->json(['organizations' => Organization::query()
            ->when($query !== '', fn ($organizations) => $organizations->where('name', 'like', '%'.$query.'%'))
            ->orderBy('name')
            ->limit(10)
            ->get(['id', 'name'])]);
    }
}
