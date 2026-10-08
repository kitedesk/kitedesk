<?php

namespace App\Http\Resources\Api\V1;

use App\Domain\KnowledgeBase\Models\Article;
use Illuminate\Http\Request;

/**
 * @mixin Article
 */
class ArticleResource extends ApiResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $this->resource->loadMissing('section.category');

        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'excerpt' => $this->excerpt,
            /** Sanitized HTML. Left out of lists. */
            'body' => $this->when(! $request->routeIs('api.v1.help-center.articles.index'), $this->body),
            /** `draft` or `published`. */
            'status' => $this->status->value,
            'section' => [
                'id' => $this->section->id,
                'name' => $this->section->name,
                'category' => ['id' => $this->section->category->id, 'name' => $this->section->category->name, 'slug' => $this->section->category->slug],
            ],
            'url' => route('help.articles.show', $this->slug),
            'view_count' => $this->view_count,
            'helpful_count' => $this->helpful_count,
            'not_helpful_count' => $this->not_helpful_count,
            'published_at' => self::time($this->published_at),
            'updated_at' => self::time($this->updated_at),
        ];
    }
}
