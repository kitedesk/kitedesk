<?php

namespace App\Http\Requests\Admin;

use App\Domain\Tickets\Models\TicketCategory;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveTicketCategoryRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $category = $this->route('ticket_category');
        $category = $category instanceof TicketCategory ? $category : null;

        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'parent_id' => [
                'nullable',
                'integer',
                Rule::exists('ticket_categories', 'id')->whereNull('parent_id'),
                Rule::notIn(array_filter([$category?->id])),
                function (string $attribute, mixed $value, Closure $fail) use ($category): void {
                    if ($value !== null && $category?->children()->exists()) {
                        $fail(__('A category with subcategories cannot become a subcategory.'));
                    }
                },
            ],
            'ticket_form_id' => ['nullable', 'integer', Rule::exists('ticket_forms', 'id')],
            'is_visible_to_customers' => ['boolean'],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * @return array{parent_id: int|null, name: string, description: string|null, ticket_form_id: int|null, is_visible_to_customers: bool, is_active: bool}
     */
    public function categoryAttributes(): array
    {
        return [
            'parent_id' => $this->validated('parent_id') !== null ? (int) $this->validated('parent_id') : null,
            'name' => $this->string('name')->toString(),
            'description' => $this->validated('description'),
            'ticket_form_id' => $this->validated('ticket_form_id') !== null ? (int) $this->validated('ticket_form_id') : null,
            'is_visible_to_customers' => $this->boolean('is_visible_to_customers', true),
            'is_active' => $this->boolean('is_active', true),
        ];
    }
}
