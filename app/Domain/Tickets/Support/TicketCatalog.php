<?php

namespace App\Domain\Tickets\Support;

use App\Domain\Tickets\Models\Ticket;
use App\Domain\Tickets\Models\TicketCategory;
use App\Domain\Tickets\Models\TicketField;
use App\Domain\Tickets\Models\TicketForm;

/**
 * Active categories and forms, shaped for the ticket pages. The client picks
 * the fields to show from the chosen category's `form_id`.
 *
 * @phpstan-type FormField array{id: int, key: string, label: string, type: string, options: list<string>|null, is_required: bool, is_visible_to_customers: bool}
 * @phpstan-type CategoryNode array{id: int, name: string, description: string, form_id: int|null}
 */
class TicketCatalog
{
    /**
     * Active categories with their active subcategories.
     *
     * @return list<array{id: int, name: string, description: string, form_id: int|null, children: list<CategoryNode>}>
     */
    public static function categories(bool $customerFacing = false): array
    {
        $default = TicketForm::default();

        $categories = TicketCategory::query()
            ->active()
            ->roots()
            ->when($customerFacing, fn ($query) => $query->visibleToCustomers())
            ->with(['form', 'children' => fn ($query) => $query->active()->when($customerFacing, fn ($children) => $children->visibleToCustomers())->with('form')])
            ->ordered()
            ->get();

        return array_values($categories->map(fn (TicketCategory $category): array => [
            ...self::node($category, $category->form, $default),
            'children' => array_values($category->children->map(
                fn (TicketCategory $child): array => self::node($child, $child->form ?? $category->form, $default),
            )->all()),
        ])->all());
    }

    /**
     * Fields of every active form, keyed by form id.
     *
     * @return array<int, list<FormField>>
     */
    public static function forms(bool $customerFacing = false): array
    {
        return TicketForm::query()
            ->active()
            ->with('fields')
            ->get()
            ->mapWithKeys(fn (TicketForm $form): array => [$form->id => self::fieldsOf($form, $customerFacing)])
            ->all();
    }

    public static function defaultFormId(): ?int
    {
        return TicketForm::default()?->id;
    }

    /**
     * Fields to show on an existing ticket: its form's fields, then any other field that has a value.
     *
     * @return list<FormField>
     */
    public static function fieldsForTicket(Ticket $ticket): array
    {
        $form = $ticket->form;
        $fields = $form !== null ? self::fieldsOf($form) : [];
        $shown = array_column($fields, 'key');
        $values = array_filter($ticket->custom_fields ?? [], fn (mixed $value): bool => $value !== null && $value !== '');

        $extra = TicketField::query()
            ->ordered()
            ->whereIn('key', array_diff(array_keys($values), $shown))
            ->get()
            ->map(fn (TicketField $field): array => $field->toFormArray(required: false))
            ->all();

        return [...$fields, ...$extra];
    }

    /**
     * @return list<FormField>
     */
    private static function fieldsOf(TicketForm $form, bool $customerFacing = false): array
    {
        return array_values($form->fields
            ->when($customerFacing, fn ($fields) => $fields->filter(fn (TicketField $field): bool => $field->is_visible_to_customers))
            ->map(fn (TicketField $field): array => $field->toFormArray())
            ->all());
    }

    /**
     * @return CategoryNode
     */
    private static function node(TicketCategory $category, ?TicketForm $form, ?TicketForm $default): array
    {
        return [
            'id' => $category->id,
            'name' => $category->name,
            'description' => $category->description ?? '',
            'form_id' => ($form !== null && $form->is_active ? $form : $default)?->id,
        ];
    }
}
