<?php

namespace App\Domain\KnowledgeBase\Models;

use App\Domain\KnowledgeBase\Enums\ArticleStatus;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Factories\ArticleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Laravel\Scout\Searchable;

/**
 * @property int $id
 * @property int $section_id
 * @property int|null $author_id
 * @property string $title
 * @property string $slug
 * @property string|null $excerpt
 * @property string $body
 * @property ArticleStatus $status
 * @property CarbonImmutable|null $published_at
 * @property int $view_count
 * @property int $helpful_count
 * @property int $not_helpful_count
 * @property int $position
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Section $section
 * @property-read User|null $author
 */
#[Fillable(['section_id', 'author_id', 'title', 'slug', 'excerpt', 'body', 'status', 'published_at', 'position'])]
#[Table('kb_articles')]
#[UseFactory(ArticleFactory::class)]
class Article extends Model
{
    /** @use HasFactory<ArticleFactory> */
    use HasFactory, Searchable;

    /**
     * @return BelongsTo<Section, $this>
     */
    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function published(Builder $query): void
    {
        $query->where('status', ArticleStatus::Published)->where('published_at', '<=', now());
    }

    public function isPublished(): bool
    {
        return $this->status === ArticleStatus::Published && $this->published_at?->lessThanOrEqualTo(now()) === true;
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * Only published articles are searchable.
     */
    public function shouldBeSearchable(): bool
    {
        return $this->isPublished();
    }

    /**
     * Get the indexable data array for the model.
     *
     * @return array<string, mixed>
     */
    public function toSearchableArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'excerpt' => $this->excerpt,
            'body' => $this->body,
        ];
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ArticleStatus::class,
            'published_at' => 'datetime',
        ];
    }
}
