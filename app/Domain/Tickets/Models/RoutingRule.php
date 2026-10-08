<?php

namespace App\Domain\Tickets\Models;

use Carbon\CarbonImmutable;
use Database\Factories\RoutingRuleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Routes new tickets: the first active rule (by position) whose conditions match sets the group,
 * and optionally the priority and extra tags.
 *
 * @property int $id
 * @property string $name
 * @property bool $is_active
 * @property 'all'|'any' $match
 * @property list<array{field: string, operator: string, value: mixed}> $conditions
 * @property array{group_id: int, priority?: string|null, tags?: list<string>} $actions
 * @property int $position
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['name', 'is_active', 'match', 'conditions', 'actions', 'position'])]
#[UseFactory(RoutingRuleFactory::class)]
class RoutingRule extends Model
{
    /** @use HasFactory<RoutingRuleFactory> */
    use HasFactory;

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function ordered(Builder $query): void
    {
        $query->orderBy('position')->orderBy('id');
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'conditions' => 'array',
            'actions' => 'array',
            'position' => 'integer',
        ];
    }
}
