<?php

namespace App\Http\Controllers\Admin\KnowledgeBase;

use App\Domain\KnowledgeBase\Models\Article;
use App\Domain\KnowledgeBase\Models\Category;
use App\Domain\KnowledgeBase\Models\Section;
use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class KnowledgeBaseController extends Controller
{
    /**
     * Category → section → article tree for managing the help center.
     */
    public function index(): Response
    {
        $categories = Category::query()
            ->ordered()
            ->with(['sections.articles' => fn ($query) => $query->select([
                'id', 'section_id', 'title', 'slug', 'status', 'published_at', 'view_count', 'helpful_count', 'not_helpful_count', 'position', 'updated_at',
            ])])
            ->get();

        return Inertia::render('admin/knowledge-base/index', [
            'categories' => $categories->map(fn (Category $category): array => [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
                'description' => $category->description,
                'sections' => $category->sections->map(fn (Section $section): array => [
                    'id' => $section->id,
                    'name' => $section->name,
                    'slug' => $section->slug,
                    'description' => $section->description,
                    'articles' => $section->articles->map(fn (Article $article): array => [
                        'id' => $article->id,
                        'title' => $article->title,
                        'slug' => $article->slug,
                        'status' => $article->status->value,
                        'is_published' => $article->isPublished(),
                        'view_count' => $article->view_count,
                        'helpful_count' => $article->helpful_count,
                        'not_helpful_count' => $article->not_helpful_count,
                        'updated_at' => $article->updated_at?->toIso8601String(),
                    ])->values(),
                ])->values(),
            ])->values(),
        ]);
    }
}
