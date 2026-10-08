<?php

namespace App\Domain\Tickets\Models;

use Carbon\CarbonImmutable;
use Database\Factories\TicketCategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Ticket categories are two levels deep: categories and their subcategories.
 *
 * @property int $id
 * @property int|null $parent_id
 * @property string $name
 * @property string|null $description
 * @property int|null $ticket_form_id
 * @property bool $is_visible_to_customers
 * @property bool $is_active
 * @property int $position
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read TicketCategory|null $parent
 * @property-read Collection<int, TicketCategory> $children
 * @property-read TicketForm|null $form
 * @property-read int|null $tickets_count
 */
#[Fillable(['parent_id', 'name', 'description', 'ticket_form_id', 'is_visible_to_customers', 'is_active', 'position'])]
#[UseFactory(TicketCategoryFactory::class)]
class TicketCategory extends Model
{
    /** @use HasFactory<TicketCategoryFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<TicketCategory, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return HasMany<TicketCategory, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('position')->orderBy('id');
    }

    /**
     * @return HasMany<Ticket, $this>
     */
    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'category_id');
    }

    /**
     * @return BelongsTo<TicketForm, $this>
     */
    public function form(): BelongsTo
    {
        return $this->belongsTo(TicketForm::class, 'ticket_form_id');
    }

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
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function roots(Builder $query): void
    {
        $query->whereNull('parent_id');
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function visibleToCustomers(Builder $query): void
    {
        $query->where('is_visible_to_customers', true);
    }

    /**
     * The form for tickets in this category: its own, else its parent's, else the default form.
     */
    public function resolveForm(): ?TicketForm
    {
        $form = $this->form ?? $this->parent?->form;

        return $form !== null && $form->is_active ? $form : TicketForm::default();
    }

    /**
     * Whether this category is the given category or one of its subcategories.
     */
    public function isWithin(int $categoryId): bool
    {
        return $this->id === $categoryId || $this->parent_id === $categoryId;
    }

    /**
     * "Category › Subcategory".
     */
    public function path(): string
    {
        return $this->parent !== null ? $this->parent->name.' › '.$this->name : $this->name;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_visible_to_customers' => 'boolean',
            'is_active' => 'boolean',
            'position' => 'integer',
        ];
    }
}
