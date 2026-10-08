<?php

namespace App\Domain\Ai\Mcp\Tools;

use App\Domain\Ai\Mcp\McpAccess;
use App\Domain\KnowledgeBase\Models\Article;
use App\Domain\Support\RichText;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('get_article')]
#[Description('Read a published help center article as plain text.')]
#[IsReadOnly]
class GetArticleTool extends Tool
{
    public function shouldRegister(Request $request): bool
    {
        return McpAccess::allows($request->user(), 'kb:read');
    }

    public function handle(Request $request): Response
    {
        $input = $request->validate(['slug' => ['required', 'string', 'max:255']]);

        $article = Article::query()->published()->where('slug', $input['slug'])->first();

        if ($article === null) {
            return Response::error(__('Article not found.'));
        }

        return Response::text($article->title."\n".route('help.articles.show', $article)."\n\n".RichText::toPlainText($article->body));
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'slug' => $schema->string()->description('The article slug, from search_articles.')->required(),
        ];
    }
}
