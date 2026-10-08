<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\KnowledgeBase\Actions\SaveHelpCenterCategory;
use App\Domain\KnowledgeBase\Actions\SaveSection;
use App\Domain\KnowledgeBase\Models\Category;
use App\Domain\KnowledgeBase\Models\Section;
use App\Http\Requests\Admin\KnowledgeBase\SaveCategoryRequest;
use App\Http\Requests\Admin\KnowledgeBase\SaveSectionRequest;
use App\Http\Resources\Api\V1\HelpCenterCategoryResource;
use App\Http\Resources\Api\V1\SectionResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * The help center's structure: categories hold sections, sections hold articles.
 *
 * @tags Help center
 */
class HelpCenterController extends ApiController
{
    /**
     * List help center categories.
     */
    public function categories(): AnonymousResourceCollection
    {
        return HelpCenterCategoryResource::collection(Category::query()->orderBy('position')->orderBy('id')->get());
    }

    /**
     * List help center sections.
     */
    public function sections(): AnonymousResourceCollection
    {
        return SectionResource::collection(Section::query()->orderBy('category_id')->orderBy('position')->orderBy('id')->get());
    }

    /**
     * Create a help center category.
     */
    public function storeCategory(SaveCategoryRequest $request, SaveHelpCenterCategory $saveCategory): JsonResponse
    {
        return (new HelpCenterCategoryResource($saveCategory->create($request->validated())))->response()->setStatusCode(201);
    }

    /**
     * Update a help center category.
     */
    public function updateCategory(SaveCategoryRequest $request, Category $category, SaveHelpCenterCategory $saveCategory): HelpCenterCategoryResource
    {
        return new HelpCenterCategoryResource($saveCategory->update($category, $request->validated()));
    }

    /**
     * Delete a help center category.
     *
     * Its sections and articles are deleted too.
     */
    public function destroyCategory(Category $category): Response
    {
        $category->delete();

        return response()->noContent();
    }

    /**
     * Create a section.
     */
    public function storeSection(SaveSectionRequest $request, SaveSection $saveSection): JsonResponse
    {
        return (new SectionResource($saveSection->create([...$request->validated(), 'category_id' => $request->integer('category_id')])))->response()->setStatusCode(201);
    }

    /**
     * Update a section.
     *
     * A section can't move to another category.
     */
    public function updateSection(SaveSectionRequest $request, Section $section, SaveSection $saveSection): SectionResource
    {
        return new SectionResource($saveSection->update($section, $request->validated()));
    }

    /**
     * Delete a section.
     *
     * Its articles are deleted too.
     */
    public function destroySection(Section $section): Response
    {
        $section->delete();

        return response()->noContent();
    }
}
