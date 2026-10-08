<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Accounts\Enums\Permission;
use App\Domain\KnowledgeBase\Actions\SaveArticle;
use App\Domain\KnowledgeBase\Models\Article;
use App\Http\Requests\Admin\KnowledgeBase\SaveArticleRequest;
use App\Http\Resources\Api\V1\ArticleResource;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * @tags Help center
 */
class ArticleController extends ApiController
{
    /**
     * List articles.
     *
     * Published articles; drafts too when the token owner manages the help center. Filter with
     * `filter[search]` (title and content), `filter[section_id]`, `filter[status]` or
     * `filter[updated_since]` (ISO 8601). Sort with `sort=title|updated_at|published_at|id`
     * (prefix `-` for descending).
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $articles = QueryBuilder::for($this->visibleArticles($request->user())->with('section.category'))
            ->allowedFilters(
                AllowedFilter::exact('section_id'),
                AllowedFilter::exact('status'),
                AllowedFilter::callback('search', fn (Builder $query, mixed $value) => $query->where(fn (Builder $search) => $search
                    ->where('title', 'like', '%'.$value.'%')
                    ->orWhere('body', 'like', '%'.$value.'%'))),
                $this->updatedSince(),
            )
            ->allowedSorts('title', 'updated_at', 'published_at', 'id')
            ->defaultSort('title', 'id')
            ->paginate($this->perPage($request))
            ->withQueryString();

        return ArticleResource::collection($articles);
    }

    /**
     * Show an article, including its HTML body.
     */
    public function show(Request $request, int $article): ArticleResource
    {
        return new ArticleResource($this->visibleArticles($request->user())->findOrFail($article));
    }

    /**
     * Create an article.
     *
     * `status` is `draft` or `published`. The slug comes from the title when left empty.
     */
    public function store(SaveArticleRequest $request, SaveArticle $saveArticle): JsonResponse
    {
        return (new ArticleResource($saveArticle->create($request->validated(), $request->user())))->response()->setStatusCode(201);
    }

    /**
     * Update an article.
     *
     * Send the full article, as when creating.
     */
    public function update(SaveArticleRequest $request, Article $article, SaveArticle $saveArticle): ArticleResource
    {
        return new ArticleResource($saveArticle->update($article, $request->validated()));
    }

    /**
     * Publish an article.
     */
    public function publish(Article $article, SaveArticle $saveArticle): ArticleResource
    {
        return new ArticleResource($saveArticle->publish($article));
    }

    /**
     * Unpublish an article.
     *
     * It goes back to being a draft and leaves the help center.
     */
    public function unpublish(Article $article, SaveArticle $saveArticle): ArticleResource
    {
        return new ArticleResource($saveArticle->unpublish($article));
    }

    /**
     * Delete an article.
     */
    public function destroy(Article $article): Response
    {
        $article->delete();

        return response()->noContent();
    }

    /**
     * @return Builder<Article>
     */
    private function visibleArticles(User $user): Builder
    {
        return Article::query()->when(! $user->hasPermission(Permission::ManageHelpCenter), fn (Builder $query) => $query->published());
    }
}
