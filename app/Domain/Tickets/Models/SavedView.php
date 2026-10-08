<?php

namespace App\Domain\Tickets\Models;

use App\Domain\Accounts\Enums\Permission;
use App\Domain\Tickets\Policies\SavedViewPolicy;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Factories\SavedViewFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A ticket list an agent saved from the queue filters. Personal views are only visible to
 * their owner; admins can share a view with every agent.
 *
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property bool $is_shared
 * @property array<string, string> $filters Same keys as the queue filters, plus `view` (the built-in base view).
 * @property string|null $sort
 * @property 'list'|'board'|null $layout Null follows the agent's preference.
 * @property int $position
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read User $owner
 */
#[Table('ticket_views')]
#[Fillable(['name', 'is_shared', 'filters', 'sort', 'layout', 'position'])]
#[UseFactory(SavedViewFactory::class)]
#[UsePolicy(SavedViewPolicy::class)]
class SavedView extends Model
{
    /** @use HasFactory<SavedViewFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Shared views plus the user's own.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function visibleTo(Builder $query, User $user): void
    {
        $query->where(fn (Builder $visible) => $visible->where('is_shared', true)->orWhere('user_id', $user->id));
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function ordered(Builder $query): void
    {
        $query->orderByDesc('is_shared')->orderBy('position')->orderBy('id');
    }

    /**
     * Owners manage their personal views; roles allowed to share manage shared ones.
     */
    public function isManageableBy(User $user): bool
    {
        return $this->is_shared ? $user->hasPermission(Permission::ShareViews) : $this->user_id === $user->id;
    }

    public function key(): string
    {
        return 'saved:'.$this->id;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_shared' => 'boolean',
            'filters' => 'array',
            'position' => 'integer',
        ];
    }
}
