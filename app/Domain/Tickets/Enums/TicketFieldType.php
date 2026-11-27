<?php

namespace App\Domain\Tickets\Enums;

enum TicketFieldType: string
{
    case Text = 'text';
    case Textarea = 'textarea';
    case Number = 'number';
    case Date = 'date';
    case Checkbox = 'checkbox';
    case Select = 'select';
    case MultiSelect = 'multiselect';

    public function label(): string
    {
        return match ($this) {
            self::Text => __('Text'),
            self::Textarea => __('Multi-line text'),
            self::Number => __('Number'),
            self::Date => __('Date'),
            self::Checkbox => __('Checkbox'),
            self::Select => __('Drop-down'),
            self::MultiSelect => __('Multi-select'),
        };
    }

    /**
     * Validation rules for a value of this field type.
     *
     * @param  list<string>  $options
     * @return list<string>
     */
    public function rules(array $options = []): array
    {
        return match ($this) {
            self::Text => ['string', 'max:255'],
            self::Textarea => ['string', 'max:10000'],
            self::Number => ['numeric'],
            self::Date => ['date'],
            self::Checkbox => ['boolean'],
            self::Select => ['string', 'in:'.implode(',', $options)],
            self::MultiSelect => ['array'],
        };
    }

    /**
     * Validation rules for each item of a value that is a list.
     *
     * @param  list<string>  $options
     * @return list<string>
     */
    public function itemRules(array $options = []): array
    {
        return match ($this) {
            self::MultiSelect => ['string', 'distinct', 'in:'.implode(',', $options)],
            default => [],
        };
    }

    /**
     * A validated value in its stored form, e.g. form posts send checkboxes as "1".
     */
    public function normalize(mixed $value): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }

        return match ($this) {
            self::Checkbox => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            self::Number => is_numeric($value) ? $value + 0 : $value,
            // Workflows set text; options never contain commas, so "a, b" means two of them.
            self::MultiSelect => array_values(array_filter(array_map(
                fn (mixed $item): string => trim((string) $item),
                is_string($value) ? explode(',', $value) : (array) $value,
            ), fn (string $item): bool => $item !== '')),
            default => $value,
        };
    }

    /**
     * Whether fields of this type need a list of options to choose from.
     */
    public function requiresOptions(): bool
    {
        return $this === self::Select || $this === self::MultiSelect;
    }

    /**
     * Whether the ticket list can be filtered by fields of this type.
     */
    public function isFilterable(): bool
    {
        return $this === self::Select || $this === self::MultiSelect || $this === self::Checkbox;
    }
}
