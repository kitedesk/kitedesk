<?php

namespace App\Domain\Ai\Mcp\Tools;

use App\Domain\Ai\Mcp\McpAccess;
use App\Domain\KnowledgeBase\Models\Article;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('search_articles')]
#[Description('Search published help center articles. Returns slugs for get_article and public URLs you can share with customers.')]
#[IsReadOnly]
class SearchArticlesTool extends Tool
{
    public function shouldRegister(Request $request): bool
    {
        return McpAccess::allows($request->user(), 'kb:read');
    }

    public function handle(Request $request): Response
    {
        $input = $request->validate([
            'query' => ['required', 'string', 'min:2', 'max:200'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:20'],
        ]);

        $articles = Article::search($input['query'])
            ->query(fn ($query) => $query->published())
            ->take($input['limit'] ?? 5)
            ->get();

        return Response::json([
            'articles' => $articles->map(fn (Article $article): array => [
                'slug' => $article->slug,
                'title' => $article->title,
                'excerpt' => $article->excerpt,
                'url' => route('help.articles.show', $article),
            ])->values()->all(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()->description('What to look for.')->required(),
            'limit' => $schema->integer()->min(1)->max(20)->description('Defaults to 5.'),
        ];
    }
}
