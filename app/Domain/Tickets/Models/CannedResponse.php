<?php

namespace App\Domain\Tickets\Models;

use App\Domain\Accounts\Models\Group;
use App\Domain\Tickets\Policies\CannedResponsePolicy;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Factories\CannedResponseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A reusable reply. Personal ones belong to one agent; admins can share a response
 * with everyone or with one group.
 *
 * @property int $id
 * @property string $title
 * @property string $body
 * @property int|null $user_id
 * @property int|null $group_id
 * @property bool $is_shared
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read User|null $user
 * @property-read Group|null $group
 */
#[Fillable(['title', 'body', 'user_id', 'group_id', 'is_shared'])]
#[UseFactory(CannedResponseFactory::class)]
#[UsePolicy(CannedResponsePolicy::class)]
class CannedResponse extends Model
{
    /** @use HasFactory<CannedResponseFactory> */
    use HasFactory;

    /**
     * Responses the agent may use: their own, shared with everyone, or shared with one of their groups.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function availableTo(Builder $query, User $user): void
    {
        $query->where(fn (Builder $available) => $available
            ->where('user_id', $user->id)
            ->orWhere('is_shared', true)
            ->orWhereIn('group_id', $user->groups()->select('groups.id')));
    }

    /**
     * Who can see it, in words: "Only you", "Everyone" or the group name.
     */
    public function isPersonal(): bool
    {
        return ! $this->is_shared && $this->group_id === null;
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Group, $this>
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
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
        ];
    }
}
