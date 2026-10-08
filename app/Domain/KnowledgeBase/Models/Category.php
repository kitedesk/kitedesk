<?php

namespace App\Domain\KnowledgeBase\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property string|null $icon
 * @property int $position
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Collection<int, Section> $sections
 * @property-read Collection<int, Article> $articles
 */
#[Fillable(['name', 'slug', 'description', 'icon', 'position'])]
#[Table('kb_categories')]
class Category extends Model
{
    /**
     * @return HasMany<Section, $this>
     */
    public function sections(): HasMany
    {
        return $this->hasMany(Section::class)->orderBy('position')->orderBy('id');
    }

    /**
     * @return HasManyThrough<Article, Section, $this>
     */
    public function articles(): HasManyThrough
    {
        return $this->hasManyThrough(Article::class, Section::class);
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function ordered(Builder $query): void
    {
        $query->orderBy('position')->orderBy('id');
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
