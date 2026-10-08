<?php

namespace App\Http\Controllers\Admin\KnowledgeBase;

use App\Domain\KnowledgeBase\Actions\SaveSection;
use App\Domain\KnowledgeBase\Models\Section;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\KnowledgeBase\MoveRequest;
use App\Http\Requests\Admin\KnowledgeBase\SaveSectionRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class SectionController extends Controller
{
    use ReordersSiblings;

    public function store(SaveSectionRequest $request, SaveSection $saveSection): RedirectResponse
    {
        $saveSection->create([...$request->validated(), 'category_id' => $request->integer('category_id')]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Section created.')]);

        return back();
    }

    public function update(SaveSectionRequest $request, Section $section, SaveSection $saveSection): RedirectResponse
    {
        $saveSection->update($section, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Section updated.')]);

        return back();
    }

    /**
     * Deleting a section also deletes its articles (cascading foreign key).
     */
    public function destroy(Section $section): RedirectResponse
    {
        $section->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Section deleted.')]);

        return back();
    }

    public function move(MoveRequest $request, Section $section): RedirectResponse
    {
        $this->moveAmongSiblings(Section::query()->where('category_id', $section->category_id), $section, $request->movesUp());

        return back();
    }
}
