<?php

namespace App\Http\Controllers\Agent;

use App\Domain\Accounts\Enums\Permission;
use App\Domain\Accounts\Models\Group;
use App\Domain\Tickets\Models\CannedResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Agent\SaveCannedResponseRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class CannedResponseController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('agent/canned-responses/index', [
            'responses' => CannedResponse::query()
                ->availableTo($user)
                ->with(['group:id,name', 'user:id,name'])
                ->orderBy('title')
                ->get()
                ->map(fn (CannedResponse $response): array => [
                    'id' => $response->id,
                    'title' => $response->title,
                    'body' => $response->body,
                    'is_shared' => $response->is_shared,
                    'group' => $response->group?->only(['id', 'name']),
                    'owner' => $response->user?->name,
                    'can_edit' => $user->can('update', $response),
                ]),
            'groups' => $user->hasPermission(Permission::ShareCannedResponses) ? Group::query()->orderBy('name')->get(['id', 'name']) : [],
        ]);
    }

    public function store(SaveCannedResponseRequest $request): RedirectResponse
    {
        CannedResponse::query()->create([...$request->responseAttributes(), 'user_id' => $request->user()->id]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Response saved.')]);

        return back();
    }

    public function update(SaveCannedResponseRequest $request, CannedResponse $cannedResponse): RedirectResponse
    {
        Gate::authorize('update', $cannedResponse);

        $cannedResponse->update($request->responseAttributes());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Response saved.')]);

        return back();
    }

    public function destroy(CannedResponse $cannedResponse): RedirectResponse
    {
        Gate::authorize('delete', $cannedResponse);

        $cannedResponse->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Response deleted.')]);

        return back();
    }
}
