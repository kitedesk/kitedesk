<?php

namespace Database\Factories;

use App\Domain\Tickets\Enums\TicketFieldType;
use App\Domain\Tickets\Models\TicketField;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<TicketField>
 */
class TicketFieldFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $label = ucfirst(fake()->unique()->word()).' '.fake()->word();

        return [
            'key' => Str::snake($label),
            'label' => $label,
            'type' => TicketFieldType::Text,
            'options' => null,
            'is_visible_to_customers' => true,
            'position' => 0,
        ];
    }

    /**
     * A drop-down with the given option values.
     *
     * @param  list<string>  $values
     */
    public function select(array $values): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => TicketFieldType::Select,
            'options' => $values,
        ]);
    }

    public function agentOnly(): static
    {
        return $this->state(fn (array $attributes) => ['is_visible_to_customers' => false]);
    }
}
