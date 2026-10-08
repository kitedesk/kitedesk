<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveTicketFormRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_default' => ['boolean'],
            'is_active' => ['boolean'],
            'fields' => ['present', 'array', 'max:100'],
            'fields.*.id' => ['required', 'integer', 'distinct', Rule::exists('ticket_fields', 'id')],
            'fields.*.is_required' => ['boolean'],
        ];
    }

    /**
     * @return array{name: string, description: string|null, is_default: bool, is_active: bool}
     */
    public function formAttributes(): array
    {
        return [
            'name' => $this->string('name')->toString(),
            'description' => $this->validated('description'),
            'is_default' => $this->boolean('is_default'),
            'is_active' => $this->boolean('is_active', true),
        ];
    }

    /**
     * @return list<array{id: int, is_required: bool}>
     */
    public function fields(): array
    {
        /** @var list<array{id: int|string, is_required?: bool}> $fields */
        $fields = $this->validated('fields', []);

        return array_map(fn (array $field): array => [
            'id' => (int) $field['id'],
            'is_required' => (bool) ($field['is_required'] ?? false),
        ], $fields);
    }
}
