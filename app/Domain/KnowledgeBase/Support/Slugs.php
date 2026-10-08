<?php

namespace App\Domain\KnowledgeBase\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Slugs
{
    /**
     * A slug that is unique within the given query, derived from the preferred value.
     *
     * @param  Builder<covariant Model>  $scope
     */
    public static function unique(Builder $scope, string $preferred, ?int $ignoreId = null): string
    {
        $base = $preferred !== '' ? $preferred : 'untitled';
        $slug = $base;
        $suffix = 2;

        while ((clone $scope)->where('slug', $slug)->when($ignoreId, fn (Builder $query) => $query->whereKeyNot($ignoreId))->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
