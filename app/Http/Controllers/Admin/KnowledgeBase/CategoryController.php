<?php

namespace App\Http\Controllers\Admin\KnowledgeBase;

use App\Domain\KnowledgeBase\Actions\SaveHelpCenterCategory;
use App\Domain\KnowledgeBase\Models\Category;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\KnowledgeBase\MoveRequest;
use App\Http\Requests\Admin\KnowledgeBase\SaveCategoryRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class CategoryController extends Controller
{
    use ReordersSiblings;

    public function store(SaveCategoryRequest $request, SaveHelpCenterCategory $saveCategory): RedirectResponse
    {
        $saveCategory->create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Category created.')]);

        return back();
    }

    public function update(SaveCategoryRequest $request, Category $category, SaveHelpCenterCategory $saveCategory): RedirectResponse
    {
        $saveCategory->update($category, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Category updated.')]);

        return back();
    }

    /**
     * Deleting a category also deletes its sections and articles (cascading foreign keys).
     */
    public function destroy(Category $category): RedirectResponse
    {
        $category->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Category deleted.')]);

        return back();
    }

    public function move(MoveRequest $request, Category $category): RedirectResponse
    {
        $this->moveAmongSiblings(Category::query(), $category, $request->movesUp());

        return back();
    }
}
