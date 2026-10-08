<?php

namespace App\Domain\Accounts\Models;

use App\Domain\Accounts\Enums\AssignmentMode;
use App\Domain\Tickets\Models\Ticket;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Factories\GroupFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property AssignmentMode $assignment_mode
 * @property int|null $last_assigned_user_id
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Collection<int, User> $agents
 * @property-read Collection<int, Ticket> $tickets
 */
#[Fillable(['name', 'description', 'assignment_mode'])]
#[UseFactory(GroupFactory::class)]
class Group extends Model
{
    /** @use HasFactory<GroupFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'assignment_mode' => 'manual',
    ];

    /**
     * @return BelongsToMany<User, $this>
     */
    public function agents(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }

    /**
     * @return HasMany<Ticket, $this>
     */
    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'assignment_mode' => AssignmentMode::class,
        ];
    }
}
