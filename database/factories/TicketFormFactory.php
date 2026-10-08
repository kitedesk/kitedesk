<?php

namespace Database\Factories;

use App\Domain\Tickets\Models\TicketField;
use App\Domain\Tickets\Models\TicketForm;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TicketForm>
 */
class TicketFormFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => ucfirst(fake()->unique()->word()).' '.fake()->word(),
            'description' => null,
            'is_default' => false,
            'is_active' => true,
        ];
    }

    public function default(): static
    {
        return $this->state(fn (array $attributes) => ['is_default' => true]);
    }

    /**
     * Attach the given fields, in order. Keys are field models, values whether they are required.
     *
     * @param  array<int, TicketField>  $fields
     * @param  list<int>  $required  Field ids that are required.
     */
    public function withFields(array $fields, array $required = []): static
    {
        return $this->afterCreating(fn (TicketForm $form) => $form->syncFields(array_values(array_map(
            fn (TicketField $field): array => ['id' => $field->id, 'is_required' => in_array($field->id, $required, true)],
            $fields,
        ))));
    }
}
