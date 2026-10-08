<?php

namespace Database\Factories;

use App\Domain\Tickets\Enums\TicketStatus;
use App\Domain\Tickets\Models\CustomStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomStatus>
 */
class CustomStatusFactory extends Factory
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
            'category' => TicketStatus::Pending,
            'color' => fake()->randomElement(CustomStatus::COLORS),
            'description' => null,
            'position' => 1,
            'is_active' => true,
        ];
    }

    public function category(TicketStatus $category): static
    {
        return $this->state(fn (array $attributes) => ['category' => $category]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['is_active' => false]);
    }
}
