<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Accounts\Actions\SaveOrganization;
use App\Domain\Accounts\Models\Organization;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveOrganizationRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OrganizationController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim($request->string('search')->toString());

        return Inertia::render('admin/organizations/index', [
            'search' => $search,
            'organizations' => Organization::query()
                ->when($search !== '', fn ($query) => $query->where('name', 'like', "%{$search}%"))
                ->withCount(['members', 'tickets'])
                ->orderBy('name')
                ->orderBy('id')
                ->paginate(25)
                ->withQueryString(),
        ]);
    }

    public function store(SaveOrganizationRequest $request, SaveOrganization $saveOrganization): RedirectResponse
    {
        $saveOrganization->create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Organization created.')]);

        return back();
    }

    public function update(SaveOrganizationRequest $request, Organization $organization, SaveOrganization $saveOrganization): RedirectResponse
    {
        $saveOrganization->update($organization, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Organization saved.')]);

        return back();
    }

    public function destroy(Organization $organization): RedirectResponse
    {
        $organization->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Organization deleted.')]);

        return back();
    }
}
