<?php

namespace Database\Factories;

use App\Domain\Accounts\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Organization>
 */
class OrganizationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'domains' => [fake()->unique()->domainName()],
            'notes' => null,
        ];
    }
}
