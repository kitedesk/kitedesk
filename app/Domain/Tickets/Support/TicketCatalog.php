<?php

namespace App\Domain\Tickets\Support;

use App\Domain\Tickets\Enums\TicketType;
use App\Domain\Tickets\Models\Ticket;
use App\Domain\Tickets\Models\TicketCategory;
use App\Domain\Tickets\Models\TicketField;
use App\Domain\Tickets\Models\TicketForm;
use Illuminate\Database\Eloquent\Collection;

/**
 * Active categories and forms, shaped for the ticket pages. The client picks
 * the fields to show from the chosen category's `form_id`; global fields are
 * part of every form and also apply when no form does.
 *
 * @phpstan-type FormField array{id: int, key: string, label: string, type: string, options: list<string>|null, is_required: bool, is_global: bool, ticket_types: list<string>|null, is_visible_to_customers: bool}
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
     * Fields of every active form (global fields included), keyed by form id.
     *
     * Customers never pick a ticket type, so their forms leave out fields limited to some types.
     * Staff forms keep them; the page shows each one once the chosen type matches.
     *
     * @return array<int, list<FormField>>
     */
    public static function forms(bool $customerFacing = false): array
    {
        return TicketForm::query()
            ->active()
            ->with('fields')
            ->get()
            ->mapWithKeys(fn (TicketForm $form): array => [$form->id => self::toArrays(self::resolve($form, null, $customerFacing, filterTypes: $customerFacing))])
            ->all();
    }

    /**
     * Global fields alone, for tickets that have no form.
     *
     * @return list<FormField>
     */
    public static function globalFields(bool $customerFacing = false): array
    {
        return self::toArrays(self::resolve(null, null, $customerFacing, filterTypes: $customerFacing));
    }

    /**
     * Fields that apply to a ticket with the given form and type: the form's fields, then
     * global fields the form doesn't have, keeping those limited to other types out.
     *
     * @return Collection<int, TicketField>
     */
    public static function fieldsFor(?TicketForm $form, TicketType|string|null $type, bool $customerFacing = false): Collection
    {
        return self::resolve($form, $type, $customerFacing, filterTypes: true);
    }

    public static function defaultFormId(): ?int
    {
        return TicketForm::default()?->id;
    }

    /**
     * Fields to show on an existing ticket: those that apply to its form and type, then any
     * other field that has a value (so changing the type never hides data).
     *
     * @return list<FormField>
     */
    public static function fieldsForTicket(Ticket $ticket): array
    {
        $fields = self::toArrays(self::fieldsFor($ticket->form, $ticket->type));
        $shown = array_column($fields, 'key');
        $values = array_filter($ticket->custom_fields ?? [], fn (mixed $value): bool => TicketField::isFilled($value));

        $extra = TicketField::query()
            ->ordered()
            ->whereIn('key', array_diff(array_keys($values), $shown))
            ->get()
            ->map(fn (TicketField $field): array => $field->toFormArray(required: false))
            ->all();

        return [...$fields, ...$extra];
    }

    /**
     * @return Collection<int, TicketField>
     */
    private static function resolve(?TicketForm $form, TicketType|string|null $type, bool $customerFacing, bool $filterTypes): Collection
    {
        /** @var Collection<int, TicketField> $fields */
        $fields = $form !== null ? $form->fields : new Collection;
        $globals = self::globals()->whereNotIn('key', $fields->pluck('key')->all());

        return $fields->concat($globals)
            ->when($customerFacing, fn (Collection $all) => $all->filter(fn (TicketField $field): bool => $field->is_visible_to_customers))
            ->when($filterTypes, fn (Collection $all) => $all->filter(fn (TicketField $field): bool => $field->appliesToType($type)))
            ->values();
    }

    /**
     * Global fields, looked up once per request.
     *
     * @return Collection<int, TicketField>
     */
    private static function globals(): Collection
    {
        return once(fn (): Collection => TicketField::query()->global()->ordered()->get());
    }

    /**
     * @param  Collection<int, TicketField>  $fields
     * @return list<FormField>
     */
    private static function toArrays(Collection $fields): array
    {
        return array_values($fields->map(fn (TicketField $field): array => $field->toFormArray())->all());
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
