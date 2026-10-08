<?php

namespace App\Http\Controllers\Admin\KnowledgeBase;

use App\Domain\KnowledgeBase\Actions\SaveArticle;
use App\Domain\KnowledgeBase\Enums\ArticleStatus;
use App\Domain\KnowledgeBase\Models\Article;
use App\Domain\KnowledgeBase\Models\Category;
use App\Domain\KnowledgeBase\Models\Section;
use App\Domain\Support\EnumOptions;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\KnowledgeBase\SaveArticleRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ArticleController extends Controller
{
    public function create(Request $request): Response
    {
        return Inertia::render('admin/knowledge-base/articles/form', [
            'article' => null,
            'sectionId' => $request->integer('section_id') ?: null,
            'sections' => $this->sectionOptions(),
            'statuses' => EnumOptions::for(ArticleStatus::class),
        ]);
    }

    public function store(SaveArticleRequest $request, SaveArticle $saveArticle): RedirectResponse
    {
        $article = $saveArticle->create($request->validated(), $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Article created.')]);

        return to_route('admin.knowledge-base.articles.edit', $article);
    }

    public function edit(Article $article): Response
    {
        return Inertia::render('admin/knowledge-base/articles/form', [
            'article' => [
                ...$article->only(['id', 'section_id', 'title', 'slug', 'excerpt', 'body']),
                'status' => $article->status->value,
                'is_published' => $article->isPublished(),
                'published_at' => $article->published_at?->toIso8601String(),
                'updated_at' => $article->updated_at?->toIso8601String(),
            ],
            'sectionId' => $article->section_id,
            'sections' => $this->sectionOptions(),
            'statuses' => EnumOptions::for(ArticleStatus::class),
        ]);
    }

    public function update(SaveArticleRequest $request, Article $article, SaveArticle $saveArticle): RedirectResponse
    {
        $saveArticle->update($article, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Article saved.')]);

        return back();
    }

    public function destroy(Article $article): RedirectResponse
    {
        $article->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Article deleted.')]);

        return to_route('admin.knowledge-base.index');
    }

    /**
     * Sections grouped under their category name, for the section picker.
     *
     * @return list<array{id: int, name: string, category: string}>
     */
    private function sectionOptions(): array
    {
        return array_values(Category::query()
            ->ordered()
            ->with('sections')
            ->get()
            ->flatMap(fn (Category $category) => $category->sections->map(fn (Section $section): array => [
                'id' => $section->id,
                'name' => $section->name,
                'category' => $category->name,
            ]))
            ->all());
    }
}
