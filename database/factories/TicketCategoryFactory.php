<?php

namespace Database\Factories;

use App\Domain\Tickets\Models\TicketCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TicketCategory>
 */
class TicketCategoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'parent_id' => null,
            'name' => ucfirst(fake()->unique()->word()),
            'description' => null,
            'ticket_form_id' => null,
            'is_visible_to_customers' => true,
            'is_active' => true,
            'position' => 0,
        ];
    }

    public function childOf(TicketCategory $parent): static
    {
        return $this->state(fn (array $attributes) => ['parent_id' => $parent->id]);
    }

    public function agentOnly(): static
    {
        return $this->state(fn (array $attributes) => ['is_visible_to_customers' => false]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['is_active' => false]);
    }
}
