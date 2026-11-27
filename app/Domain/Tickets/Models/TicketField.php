<?php

namespace App\Domain\Tickets\Models;

use App\Domain\Audit\Concerns\Auditable;
use App\Domain\Tickets\Enums\TicketFieldType;
use App\Domain\Tickets\Enums\TicketType;
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
 * @property bool $is_global Shown on every ticket, whatever its form.
 * @property bool $is_required Required when shown as a global field; form fields are required per form.
 * @property list<string>|null $ticket_types Ticket types the field applies to; null for all types.
 * @property bool $is_visible_to_customers
 * @property int $position
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read TicketFormFieldPivot|null $pivot
 */
#[Fillable(['key', 'label', 'type', 'options', 'is_global', 'is_required', 'ticket_types', 'is_visible_to_customers', 'position'])]
#[UseFactory(TicketFieldFactory::class)]
class TicketField extends Model
{
    /** @use HasFactory<TicketFieldFactory> */
    use Auditable, HasFactory;

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
    protected function global(Builder $query): void
    {
        $query->where('is_global', true);
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
     * Validation rules for each item of a list value (multi-select), or none.
     *
     * @return list<string>
     */
    public function itemRules(): array
    {
        return $this->type->itemRules($this->options ?? []);
    }

    /**
     * Rules for this field's value under `$prefix` (e.g. `custom_fields`), including its items.
     *
     * @param  list<string>  $leading  Rules placed before the value rules, such as `sometimes`.
     * @return array<string, list<string>>
     */
    public function valueRules(string $prefix, bool $required = false, array $leading = []): array
    {
        $rules = ["{$prefix}.{$this->key}" => [...$leading, ...$this->rules($required)]];

        if ($this->itemRules() !== []) {
            $rules["{$prefix}.{$this->key}.*"] = $this->itemRules();
        }

        return $rules;
    }

    /**
     * Values keyed by field key in their stored form; keys of unknown fields are kept as they are.
     *
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    public static function normalizeValues(array $values): array
    {
        if ($values === []) {
            return [];
        }

        $types = self::query()->whereIn('key', array_keys($values))->pluck('type', 'key');

        return collect($values)
            ->map(fn (mixed $value, string $key): mixed => $types->has($key) ? $types[$key]->normalize($value) : $value)
            ->all();
    }

    /**
     * Whether the field shows on tickets of the given type.
     */
    public function appliesToType(TicketType|string|null $type): bool
    {
        if ($this->ticket_types === null || $this->ticket_types === []) {
            return true;
        }

        $value = $type instanceof TicketType ? $type->value : $type;

        return $value !== null && in_array($value, $this->ticket_types, true);
    }

    /**
     * Whether a value counts as filled in, as Laravel's `required` rule sees it.
     */
    public static function isFilled(mixed $value): bool
    {
        return ! ($value === null || (is_string($value) && trim($value) === '') || $value === []);
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
     * @return array{id: int, key: string, label: string, type: string, options: list<string>|null, is_required: bool, is_global: bool, ticket_types: list<string>|null, is_visible_to_customers: bool}
     */
    public function toFormArray(?bool $required = null): array
    {
        return [
            'id' => $this->id,
            'key' => $this->key,
            'label' => $this->label,
            'type' => $this->type->value,
            'options' => $this->options,
            'is_required' => $required ?? $this->isRequired(),
            'is_global' => $this->is_global,
            'ticket_types' => $this->ticket_types,
            'is_visible_to_customers' => $this->is_visible_to_customers,
        ];
    }

    /**
     * Required by the form it was loaded through, or by its own flag as a global field.
     */
    public function isRequired(): bool
    {
        return (bool) $this->pivot?->is_required || ($this->is_global && $this->is_required);
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
            'is_global' => 'boolean',
            'is_required' => 'boolean',
            'ticket_types' => 'array',
            'is_visible_to_customers' => 'boolean',
        ];
    }
}
