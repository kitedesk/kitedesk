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

    public function label(): string
    {
        return match ($this) {
            self::Text => __('Text'),
            self::Textarea => __('Multi-line text'),
            self::Number => __('Number'),
            self::Date => __('Date'),
            self::Checkbox => __('Checkbox'),
            self::Select => __('Drop-down'),
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
        };
    }
}
