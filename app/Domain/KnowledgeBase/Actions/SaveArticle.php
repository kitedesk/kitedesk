<?php

namespace App\Domain\KnowledgeBase\Actions;

use App\Domain\KnowledgeBase\Enums\ArticleStatus;
use App\Domain\KnowledgeBase\Models\Article;
use App\Domain\Support\RichText;
use App\Domain\Webhooks\Enums\WebhookEvent;
use App\Domain\Webhooks\Support\Webhooks;
use App\Http\Resources\Api\V1\ArticleResource;
use App\Models\User;

/**
 * Creates, updates, publishes and unpublishes help center articles. The body is sanitized,
 * publishing for the first time stamps the publication date (drafts keep it for history), and
 * webhooks hear when an article goes live.
 */
class SaveArticle
{
    /**
     * @param  array<string, mixed>  $attributes  Validated by `SaveArticleRequest`.
     */
    public function create(array $attributes, User $author): Article
    {
        $article = new Article($this->attributes($attributes));
        $article->author_id = $author->id;
        $article->position = (int) Article::query()->where('section_id', $article->section_id)->max('position') + 1;

        return $this->save($article, wasPublished: false);
    }

    /**
     * @param  array<string, mixed>  $attributes  Validated by `SaveArticleRequest`.
     */
    public function update(Article $article, array $attributes): Article
    {
        $wasPublished = $article->isPublished();

        return $this->save($article->fill($this->attributes($attributes)), $wasPublished);
    }

    public function publish(Article $article): Article
    {
        $wasPublished = $article->isPublished();
        $article->status = ArticleStatus::Published;

        return $this->save($article, $wasPublished);
    }

    public function unpublish(Article $article): Article
    {
        $article->status = ArticleStatus::Draft;

        return $this->save($article, wasPublished: true);
    }

    private function save(Article $article, bool $wasPublished): Article
    {
        if ($article->status === ArticleStatus::Published && $article->published_at === null) {
            $article->published_at = now();
        }

        $article->save();

        if (! $wasPublished && $article->isPublished()) {
            Webhooks::send(WebhookEvent::ArticlePublished, fn (): array => ['article' => (new ArticleResource($article->load('section.category')))->resolve()]);
        }

        return $article;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function attributes(array $attributes): array
    {
        return [
            'section_id' => (int) $attributes['section_id'],
            'title' => (string) $attributes['title'],
            'slug' => (string) $attributes['slug'],
            'excerpt' => $attributes['excerpt'] ?? null,
            'body' => RichText::sanitize((string) $attributes['body']),
            'status' => ArticleStatus::from((string) $attributes['status']),
        ];
    }
}
