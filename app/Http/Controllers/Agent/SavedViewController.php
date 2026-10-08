<?php

namespace App\Http\Controllers\Agent;

use App\Domain\Support\Positions;
use App\Domain\Tickets\Models\SavedView;
use App\Http\Controllers\Controller;
use App\Http\Requests\Agent\SaveSavedViewRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

/**
 * Agents save queue filters as personal views; admins can share them with everyone.
 */
class SavedViewController extends Controller
{
    public function store(SaveSavedViewRequest $request): RedirectResponse
    {
        $isShared = $request->boolean('is_shared');

        $view = $request->user()->savedViews()->create([
            'name' => $request->string('name')->toString(),
            'is_shared' => $isShared,
            'filters' => $request->storedFilters(),
            'sort' => $request->validated('sort'),
            'layout' => $request->validated('layout'),
            'position' => (int) SavedView::query()
                ->where('is_shared', $isShared)
                ->when(! $isShared, fn ($query) => $query->where('user_id', $request->user()->id))
                ->max('position') + 1,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('View saved.')]);

        return to_route('agent.tickets.index', ['view' => $view->key()]);
    }

    public function update(SaveSavedViewRequest $request, SavedView $view): RedirectResponse
    {
        Gate::authorize('update', $view);

        $view->update($request->safe()->only(['name', 'is_shared', 'sort', 'layout']));

        return back();
    }

    public function destroy(SavedView $view): RedirectResponse
    {
        Gate::authorize('delete', $view);

        $view->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('View deleted.')]);

        return back();
    }

    /**
     * Move a view up or down within its section (shared or personal).
     */
    public function move(Request $request, SavedView $view): RedirectResponse
    {
        Gate::authorize('update', $view);

        /** @var 'up'|'down' $direction */
        $direction = $request->validate(['direction' => ['required', 'in:up,down']])['direction'];

        Positions::move(
            SavedView::query()->where('is_shared', $view->is_shared)->when(! $view->is_shared, fn ($query) => $query->where('user_id', $view->user_id)),
            $view,
            $direction,
        );

        return back();
    }
}
