<?php

namespace App\Domain\Tickets\Models;

use App\Domain\Tickets\Enums\TicketStatus;
use App\Domain\Tickets\Support\CustomStatuses;
use Carbon\CarbonImmutable;
use Database\Factories\CustomStatusFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A status admins define within one of the fixed categories ("Waiting on vendor" under
 * Pending). The category drives all ticket behavior; the status adds a name and color.
 *
 * @property int $id
 * @property string|null $name
 * @property TicketStatus $category
 * @property string $color
 * @property string|null $description
 * @property int $position
 * @property bool $is_default
 * @property bool $is_active
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Table('ticket_statuses')]
#[Fillable(['name', 'category', 'color', 'description', 'position', 'is_active'])]
#[UseFactory(CustomStatusFactory::class)]
class CustomStatus extends Model
{
    /** @use HasFactory<CustomStatusFactory> */
    use HasFactory;

    /**
     * Colors admins can pick from; the frontend maps each one to badge and dot classes.
     */
    public const array COLORS = ['slate', 'zinc', 'amber', 'orange', 'rose', 'violet', 'blue', 'sky', 'teal', 'emerald'];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'color' => 'slate',
        'position' => 0,
        'is_default' => false,
        'is_active' => true,
    ];

    protected static function booted(): void
    {
        static::saved(fn () => CustomStatuses::flush());
        static::deleted(fn () => CustomStatuses::flush());
    }

    /**
     * @return HasMany<Ticket, $this>
     */
    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'ticket_status_id');
    }

    /**
     * Categories in their workflow order, then the admin's order within each category.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function ordered(Builder $query): void
    {
        $query->orderByRaw("case category when 'new' then 0 when 'open' then 1 when 'pending' then 2 when 'on_hold' then 3 when 'solved' then 4 else 5 end")->orderBy('position')->orderBy('id');
    }

    /**
     * The admin's name, or the translated category name for an unrenamed default status.
     */
    public function label(): string
    {
        return $this->name ?? $this->category->label();
    }

    /**
     * @return array{id: int, name: string, color: string, category: string}
     */
    public function toSummary(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->label(),
            'color' => $this->color,
            'category' => $this->category->value,
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
            'category' => TicketStatus::class,
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
