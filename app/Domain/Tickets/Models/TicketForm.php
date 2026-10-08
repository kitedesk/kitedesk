<?php

namespace App\Domain\Tickets\Models;

use Carbon\CarbonImmutable;
use Database\Factories\TicketFormFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A set of ticket fields shown together. Categories pick the form a ticket uses.
 *
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property bool $is_default
 * @property bool $is_active
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Collection<int, TicketField> $fields
 * @property-read Collection<int, TicketCategory> $categories
 * @property-read int|null $fields_count
 * @property-read int|null $categories_count
 */
#[Fillable(['name', 'description', 'is_default', 'is_active'])]
#[UseFactory(TicketFormFactory::class)]
class TicketForm extends Model
{
    /** @use HasFactory<TicketFormFactory> */
    use HasFactory;

    /**
     * The form used when a ticket has no category, or its category has no form.
     */
    public static function default(): ?self
    {
        return self::query()->active()->where('is_default', true)->first();
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
     * @return BelongsToMany<TicketField, $this, TicketFormFieldPivot>
     */
    public function fields(): BelongsToMany
    {
        return $this->belongsToMany(TicketField::class, 'ticket_form_field')
            ->using(TicketFormFieldPivot::class)
            ->withPivot(['position', 'is_required'])
            ->orderByPivot('position');
    }

    /**
     * @return HasMany<TicketCategory, $this>
     */
    public function categories(): HasMany
    {
        return $this->hasMany(TicketCategory::class);
    }

    /**
     * Replace the form's fields, keeping the given order.
     *
     * @param  list<array{id: int, is_required: bool}>  $fields
     */
    public function syncFields(array $fields): void
    {
        $this->fields()->sync(collect($fields)->values()->mapWithKeys(fn (array $field, int $position): array => [
            $field['id'] => ['position' => $position, 'is_required' => $field['is_required']],
        ])->all());
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
