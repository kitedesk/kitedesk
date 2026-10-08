<?php

namespace App\Domain\Tickets\Models;

use App\Domain\Tickets\Enums\TicketFieldType;
use Carbon\CarbonImmutable;
use Database\Factories\TicketFieldFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property int $id
 * @property string $key
 * @property string $label
 * @property TicketFieldType $type
 * @property list<string>|null $options
 * @property bool $is_visible_to_customers
 * @property int $position
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read TicketFormFieldPivot|null $pivot
 */
#[Fillable(['key', 'label', 'type', 'options', 'is_visible_to_customers', 'position'])]
#[UseFactory(TicketFieldFactory::class)]
class TicketField extends Model
{
    /** @use HasFactory<TicketFieldFactory> */
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
     * @return BelongsToMany<TicketForm, $this, TicketFormFieldPivot>
     */
    public function forms(): BelongsToMany
    {
        return $this->belongsToMany(TicketForm::class, 'ticket_form_field')
            ->using(TicketFormFieldPivot::class)
            ->withPivot(['position', 'is_required']);
    }

    /**
     * Validation rules for this field's value.
     *
     * @return list<string>
     */
    public function rules(bool $required = false): array
    {
        return [$required ? 'required' : 'nullable', ...$this->type->rules($this->options ?? [])];
    }

    /**
     * Keys of the fields customers may see, looked up once per request.
     *
     * @return list<string>
     */
    public static function customerVisibleKeys(): array
    {
        return once(fn (): array => array_values(self::query()->where('is_visible_to_customers', true)->pluck('key')->all()));
    }

    /**
     * The field as shown on a form or the ticket panel.
     *
     * @return array{id: int, key: string, label: string, type: string, options: list<string>|null, is_required: bool, is_visible_to_customers: bool}
     */
    public function toFormArray(?bool $required = null): array
    {
        return [
            'id' => $this->id,
            'key' => $this->key,
            'label' => $this->label,
            'type' => $this->type->value,
            'options' => $this->options,
            'is_required' => $required ?? (bool) $this->pivot?->is_required,
            'is_visible_to_customers' => $this->is_visible_to_customers,
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
            'type' => TicketFieldType::class,
            'options' => 'array',
            'is_visible_to_customers' => 'boolean',
        ];
    }
}
