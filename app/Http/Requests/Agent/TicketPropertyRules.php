<?php

namespace App\Http\Requests\Agent;

use App\Domain\Tickets\Enums\TicketPriority;
use App\Domain\Tickets\Enums\TicketStatus;
use App\Domain\Tickets\Enums\TicketType;
use App\Domain\Tickets\Models\TicketCategory;
use App\Domain\Tickets\Models\TicketField;
use App\Domain\Tickets\Models\TicketForm;
use App\Rules\AssignableAgent;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\Rule;

/**
 * Validation rules shared by every request that creates or edits ticket properties.
 */
trait TicketPropertyRules
{
    /**
     * @param  bool  $creating  New tickets validate custom fields against the category's form, including required fields.
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    protected function ticketPropertyRules(bool $creating = false): array
    {
        return [
            'status' => ['sometimes', Rule::enum(TicketStatus::class)],
            'ticket_status_id' => ['sometimes', 'integer', Rule::exists('ticket_statuses', 'id')->where('is_active', true)],
            'priority' => ['sometimes', Rule::enum(TicketPriority::class)],
            'type' => ['sometimes', 'nullable', Rule::enum(TicketType::class)],
            'assignee_id' => ['sometimes', 'nullable', 'integer', new AssignableAgent],
            'group_id' => ['sometimes', 'nullable', 'integer', Rule::exists('groups', 'id')],
            'tags' => ['sometimes', 'array', 'max:20'],
            'tags.*' => ['string', 'max:50'],
            'collaborator_ids' => ['sometimes', 'array', 'max:50'],
            'collaborator_ids.*' => ['integer', 'distinct', Rule::exists('users', 'id')],
            ...$this->categoryRules(),
            ...($creating ? $this->customFieldRules() : $this->editableCustomFieldRules()),
        ];
    }

    /**
     * @return array<string, array<mixed>>
     */
    protected function categoryRules(bool $required = false, bool $customerFacing = false): array
    {
        return [
            'category_id' => [
                $required ? 'required' : 'sometimes',
                'nullable',
                'integer',
                Rule::exists('ticket_categories', 'id')
                    ->where('is_active', true)
                    ->when($customerFacing, fn ($rule) => $rule->where('is_visible_to_customers', true)),
                $this->requiresSubcategory($customerFacing),
            ],
        ];
    }

    /**
     * Rules for a new ticket's custom fields, from the form of the chosen category (or the default form).
     *
     * @return array<string, array<mixed>>
     */
    protected function customFieldRules(bool $customerFacing = false): array
    {
        $fields = $this->formFields($customerFacing);

        $rules = ['custom_fields' => $fields->isEmpty()
            ? ['prohibited']
            : ['sometimes', 'array:'.$fields->pluck('key')->implode(',')]];

        foreach ($fields as $field) {
            $rules["custom_fields.{$field->key}"] = $field->rules((bool) $field->pivot?->is_required);
        }

        return $rules;
    }

    /**
     * Rules for editing custom fields on an existing ticket: any field, nothing required.
     *
     * @return array<string, array<mixed>>
     */
    protected function editableCustomFieldRules(): array
    {
        $fields = TicketField::query()->get();
        $rules = ['custom_fields' => ['sometimes', 'array:'.$fields->pluck('key')->implode(',')]];

        foreach ($fields as $field) {
            $rules["custom_fields.{$field->key}"] = ['sometimes', ...$field->rules()];
        }

        return $rules;
    }

    /**
     * The category picked in this request, when it exists and is active.
     */
    protected function selectedCategory(): ?TicketCategory
    {
        $id = $this->input('category_id');

        return is_numeric($id)
            ? TicketCategory::query()->active()->with(['parent.form', 'form'])->find((int) $id)
            : null;
    }

    /**
     * Fields of the form a new ticket in the selected category uses.
     *
     * @return Collection<int, TicketField>
     */
    protected function formFields(bool $customerFacing = false): Collection
    {
        $category = $this->selectedCategory();
        $form = $category !== null ? $category->resolveForm() : TicketForm::default();

        /** @var Collection<int, TicketField> $fields */
        $fields = $form?->fields()->get() ?? new Collection;

        return $customerFacing
            ? $fields->filter(fn (TicketField $field): bool => $field->is_visible_to_customers)->values()
            : $fields;
    }

    /**
     * Categories with active subcategories must be narrowed down to one of them.
     */
    private function requiresSubcategory(bool $customerFacing): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($customerFacing): void {
            if (! is_numeric($value)) {
                return;
            }

            $hasChildren = TicketCategory::query()
                ->active()
                ->where('parent_id', (int) $value)
                ->when($customerFacing, fn ($query) => $query->visibleToCustomers())
                ->exists();

            if ($hasChildren) {
                $fail(__('Please choose a subcategory.'));
            }
        };
    }
}
