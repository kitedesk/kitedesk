<?php

namespace App\Http\Requests\Admin;

use App\Domain\Tickets\Enums\TicketFieldType;
use App\Domain\Tickets\Models\TicketField;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SaveTicketFieldRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (! $this->filled('key') && $this->filled('label')) {
            $this->merge(['key' => Str::snake(Str::ascii((string) $this->input('label')))]);
        }

        $options = $this->input('options');

        if (is_string($options) || is_array($options)) {
            $this->merge([
                'options' => collect(is_string($options) ? explode("\n", $options) : $options)
                    ->map(fn (mixed $option): string => trim((string) $option))
                    ->filter()
                    ->unique()
                    ->values()
                    ->all(),
            ]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $field = $this->route('ticket_field');

        return [
            'label' => ['required', 'string', 'max:255'],
            'key' => ['required', 'string', 'max:64', 'regex:/^[a-z][a-z0-9_]*$/', Rule::unique('ticket_fields', 'key')->ignore($field instanceof TicketField ? $field->id : null)],
            'type' => [$field instanceof TicketField ? 'prohibited' : 'required', Rule::enum(TicketFieldType::class)],
            'options' => [Rule::requiredIf(fn (): bool => ($field instanceof TicketField ? $field->type->value : $this->input('type')) === TicketFieldType::Select->value), 'nullable', 'array', 'max:200'],
            'options.*' => ['string', 'max:100', 'not_regex:/,/'],
            'is_visible_to_customers' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'type.prohibited' => __('The type of an existing field cannot be changed.'),
            'options.*.not_regex' => __('Options cannot contain commas.'),
        ];
    }
}
