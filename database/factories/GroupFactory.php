<?php

namespace Database\Factories;

use App\Domain\Accounts\Models\Group;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Group>
 */
class GroupFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->randomElement(['Support', 'Billing', 'Technical', 'Sales', 'Onboarding', 'Escalations']).' '.fake()->unique()->numberBetween(1, 9999),
            'description' => fake()->sentence(),
        ];
    }
}
