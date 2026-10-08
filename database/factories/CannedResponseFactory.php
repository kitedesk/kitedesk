<?php

namespace Database\Factories;

use App\Domain\Tickets\Models\CannedResponse;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CannedResponse>
 */
class CannedResponseFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => ucfirst(fake()->word()).' '.fake()->word(),
            'body' => '<p>Hi {{requester.first_name}}, '.fake()->sentence().'</p>',
            'user_id' => User::factory()->agent(),
            'group_id' => null,
            'is_shared' => false,
        ];
    }

    public function shared(): static
    {
        return $this->state(fn (array $attributes) => ['user_id' => null, 'is_shared' => true]);
    }
}
