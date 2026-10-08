<?php

namespace App\Http\Controllers\Help;

use App\Domain\KnowledgeBase\Models\Article;
use App\Domain\KnowledgeBase\Models\Category;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HelpCenterController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('help/index', [
            'categories' => Category::query()
                ->ordered()
                ->withCount(['articles' => fn ($query) => $query->published()])
                ->get()
                ->filter(fn (Category $category): bool => $category->articles_count > 0)
                ->values(),
            'popularArticles' => Article::query()
                ->published()
                ->orderByDesc('view_count')
                ->orderByDesc('id')
                ->limit(6)
                ->get(['id', 'title', 'slug', 'excerpt']),
        ]);
    }

    public function category(Category $category): Response
    {
        $category->load(['sections.articles' => fn ($query) => $query->published()->select(['id', 'section_id', 'title', 'slug', 'excerpt'])]);

        return Inertia::render('help/category', [
            'category' => $category->only(['id', 'name', 'slug', 'description']),
            'sections' => $category->sections
                ->filter(fn ($section): bool => $section->articles->isNotEmpty())
                ->map(fn ($section): array => [
                    'id' => $section->id,
                    'name' => $section->name,
                    'description' => $section->description,
                    'articles' => $section->articles,
                ])
                ->values(),
        ]);
    }

    public function article(Article $article): Response
    {
        abort_unless($article->isPublished(), 404);

        $article->increment('view_count');
        $article->load('section.category');

        return Inertia::render('help/article', [
            'article' => [
                ...$article->only(['id', 'title', 'slug', 'excerpt', 'body', 'helpful_count', 'not_helpful_count']),
                'updated_at' => $article->updated_at?->toIso8601String(),
                'section' => $article->section->only(['id', 'name']),
                'category' => $article->section->category->only(['id', 'name', 'slug']),
            ],
            'related' => $article->section->articles()
                ->published()
                ->whereKeyNot($article->id)
                ->limit(5)
                ->get(['id', 'title', 'slug']),
        ]);
    }

    /**
     * Full-text search over published articles (also powers deflection while customers type a request).
     */
    public function search(Request $request): Response|JsonResponse
    {
        $term = trim($request->string('q')->toString());

        $articles = $term === ''
            ? collect()
            : Article::search($term)
                ->query(fn ($query) => $query->published()->select(['id', 'title', 'slug', 'excerpt']))
                ->take(10)
                ->get();

        if ($request->wantsJson()) {
            return response()->json(['articles' => $articles->values()]);
        }

        return Inertia::render('help/search', [
            'query' => $term,
            'articles' => $articles->values(),
        ]);
    }

    public function feedback(Request $request, Article $article): JsonResponse
    {
        abort_unless($article->isPublished(), 404);

        $validated = $request->validate(['helpful' => ['required', 'boolean']]);

        $article->increment($validated['helpful'] ? 'helpful_count' : 'not_helpful_count');

        return response()->json(['ok' => true]);
    }
}
