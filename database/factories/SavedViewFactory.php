<?php

namespace Database\Factories;

use App\Domain\Tickets\Models\SavedView;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SavedView>
 */
class SavedViewFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->agent(),
            'name' => ucfirst(fake()->word()).' '.fake()->word(),
            'is_shared' => false,
            'filters' => ['view' => 'all'],
            'sort' => null,
            'position' => 0,
        ];
    }

    public function shared(): static
    {
        return $this->state(fn (array $attributes) => ['is_shared' => true]);
    }

    /**
     * @param  array<string, string>  $filters
     */
    public function filtering(array $filters): static
    {
        return $this->state(fn (array $attributes) => ['filters' => ['view' => 'all', ...$filters]]);
    }
}
