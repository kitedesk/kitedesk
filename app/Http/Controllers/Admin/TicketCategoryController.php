<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Support\Positions;
use App\Domain\Tickets\Actions\Setup\SaveTicketCategory;
use App\Domain\Tickets\Models\TicketCategory;
use App\Domain\Tickets\Models\TicketForm;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveTicketCategoryRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TicketCategoryController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/ticket-categories/index', [
            'categories' => TicketCategory::query()
                ->roots()
                ->ordered()
                ->with('children')
                ->get()
                ->map(fn (TicketCategory $category): array => [
                    ...$this->serialize($category),
                    'children' => $category->children->map(fn (TicketCategory $child): array => $this->serialize($child))->values(),
                ]),
            'forms' => TicketForm::query()->orderByDesc('is_default')->orderBy('id')->get()->map(fn (TicketForm $form): array => [
                'id' => $form->id,
                'name' => $form->name,
                'is_default' => $form->is_default,
            ]),
        ]);
    }

    public function store(SaveTicketCategoryRequest $request, SaveTicketCategory $saveCategory): RedirectResponse
    {
        $saveCategory->create($request->categoryAttributes());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Category created.')]);

        return back();
    }

    public function update(SaveTicketCategoryRequest $request, TicketCategory $ticketCategory, SaveTicketCategory $saveCategory): RedirectResponse
    {
        $saveCategory->update($ticketCategory, $request->categoryAttributes());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Category saved.')]);

        return back();
    }

    public function destroy(TicketCategory $ticketCategory): RedirectResponse
    {
        $ticketCategory->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Category deleted. Its tickets keep their fields but no longer have a category.')]);

        return back();
    }

    /**
     * Move a category one step up or down among its siblings.
     */
    public function move(Request $request, TicketCategory $ticketCategory): RedirectResponse
    {
        /** @var 'up'|'down' $direction */
        $direction = $request->validate(['direction' => ['required', 'in:up,down']])['direction'];

        Positions::move(TicketCategory::query()->where('parent_id', $ticketCategory->parent_id), $ticketCategory, $direction);

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(TicketCategory $category): array
    {
        return [
            'id' => $category->id,
            'parent_id' => $category->parent_id,
            'name' => $category->name,
            'description' => $category->description,
            'ticket_form_id' => $category->ticket_form_id,
            'is_visible_to_customers' => $category->is_visible_to_customers,
            'is_active' => $category->is_active,
        ];
    }
}
